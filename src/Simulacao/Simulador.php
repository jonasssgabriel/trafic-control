<?php

namespace App\Simulacao;

use App\Entidades\Veiculo;
use App\Enums\Direcao;
use App\Enums\StatusVeiculo;
use App\Infraestrutura\Cruzamento;

class Simulador
{
    private const LARGURA_MUNDO = 22;
    private const ALTURA_MUNDO = 22;
    private const PISTA_LESTE_Y = 10;
    private const PISTA_OESTE_Y = 11;
    private const PISTA_SUL_X = 10;
    private const PISTA_NORTE_X = 11;

    /** @var Veiculo[] */
    private array $veiculos = [];
    private Cruzamento $cruzamento;
    private array $logMestre = [];
    private int $passoAtual = 0;

    public function __construct(int $numVeiculosIniciais)
    {
        $this->cruzamento = new Cruzamento(self::PISTA_SUL_X, self::PISTA_LESTE_Y);
        $this->_gerarVeiculosIniciais($numVeiculosIniciais);
    }

    public function rodar(int $totalPassos, int $tempoResolucaoAcidente): void
    {
        for ($i = 1; $i <= $totalPassos; $i++) {
            $this->passoAtual = $i;
            $this->_executarPasso($tempoResolucaoAcidente);
            $this->_desenharGridNoConsole();
            usleep(200000);
        }
    }

    private function _executarPasso(int $tempoResolucaoAcidente): void
    {
        $eventosDoPasso = [];
        $this->cruzamento->atualizarSemaforos();
        $this->_atualizarStatusDeColisoes($eventosDoPasso);
        $planosDeVirada = [];
        $planosDeMovimento = $this->_planejarMovimentos($eventosDoPasso, $planosDeVirada);
        $this->_resolverConflitosEMover($planosDeMovimento, $tempoResolucaoAcidente, $eventosDoPasso, $planosDeVirada);
        $novosLogsFormatados = [];
        foreach ($eventosDoPasso as $evento) {
            $novosLogsFormatados[] = ['passo' => $this->passoAtual, 'evento' => $evento];
        }
        $this->logMestre = array_merge($novosLogsFormatados, $this->logMestre);
    }

    private function _atualizarStatusDeColisoes(array &$eventosDoPasso): void
    {
        $veiculosAindaAtivos = [];
        foreach ($this->veiculos as $veiculo) {
            if ($veiculo->estaColidido() && $veiculo->tickColisao()) {
                $eventosDoPasso[] = "ACIDENTE RESOLVIDO na posição ({$veiculo->posicaoX}, {$veiculo->posicaoY}). Veículo #{$veiculo->id} removido.";
            } else {
                $veiculosAindaAtivos[] = $veiculo;
            }
        }
        $this->veiculos = $veiculosAindaAtivos;
    }
    
    // --- CORREÇÃO PRINCIPAL ESTÁ NESTE MÉTODO ---
    private function _planejarMovimentos(array &$eventosDoPasso, array &$planosDeVirada): array
    {
        $planosDeMovimento = [];
        $posicoesOcupadas = $this->_obterMapaDePosicoes();

        foreach ($this->veiculos as $veiculo) {
            if ($veiculo->estaColidido()) continue;

            $pontoDeDecisao = $this->_checarPontoDeDecisao($veiculo);
            if ($pontoDeDecisao && mt_rand(1, 100) <= 30) {
                $novaDirecao = $this->_obterViradaValida($veiculo->direcao);
                $planosDeVirada[$veiculo->id] = $novaDirecao;
                $eventosDoPasso[] = "Veículo #{$veiculo->id} planeja virar para {$novaDirecao->value}.";
            }
            
            if (mt_rand(1, 1000) === 1) {
                $this->_registrarColisao([$veiculo], "acidente espontâneo", 20, $eventosDoPasso);
                continue;
            }

            $proximaPos = $veiculo->obterProximaPosicao();
            if ($proximaPos['x'] < 0) $proximaPos['x'] = self::LARGURA_MUNDO - 1;
            if ($proximaPos['x'] >= self::LARGURA_MUNDO) $proximaPos['x'] = 0;
            if ($proximaPos['y'] < 0) $proximaPos['y'] = self::ALTURA_MUNDO - 1;
            if ($proximaPos['y'] >= self::ALTURA_MUNDO) $proximaPos['y'] = 0;

            // --- LÓGICA DO SEMÁFORO CORRIGIDA ---
            // 1. Verifica se o veículo já está DENTRO da área do cruzamento.
            $estaDentroDoCruzamento = ($veiculo->posicaoX >= self::PISTA_SUL_X && $veiculo->posicaoX <= self::PISTA_NORTE_X) &&
                                      ($veiculo->posicaoY >= self::PISTA_LESTE_Y && $veiculo->posicaoY <= self::PISTA_OESTE_Y);
            
            // 2. Verifica se o veículo está PRESTES A ENTRAR no cruzamento.
            $vaiEntrarNoCruzamento = ($proximaPos['x'] >= self::PISTA_SUL_X && $proximaPos['x'] <= self::PISTA_NORTE_X) &&
                                     ($proximaPos['y'] >= self::PISTA_LESTE_Y && $proximaPos['y'] <= self::PISTA_OESTE_Y);

            // A regra do semáforo só se aplica se o veículo está FORA e vai ENTRAR.
            if (!$estaDentroDoCruzamento && $vaiEntrarNoCruzamento) {
                if (!$this->cruzamento->podePassar($veiculo->direcao)) {
                    // 5% de chance de furar o sinal
                    if (mt_rand(1, 100) <= 5) {
                        $eventosDoPasso[] = "Veículo #{$veiculo->id} furou o sinal vermelho!";
                    } else {
                        // Para ANTES de entrar no cruzamento.
                        continue; 
                    }
                }
            }
            // Se o veículo já está dentro do cruzamento, ele ignora o sinal e continua.
            // --- FIM DA LÓGICA CORRIGIDA ---

            $proximaPosStr = "{$proximaPos['x']}:{$proximaPos['y']}";
            if(isset($posicoesOcupadas[$proximaPosStr])) {
                continue;
            }

            $planosDeMovimento[$veiculo->id] = $proximaPos;
        }
        return $planosDeMovimento;
    }
    
    private function _resolverConflitosEMover(array $planos, int $tempoResolucaoAcidente, array &$eventosDoPasso, array $planosDeVirada): void
    {
        $celulasAlvo = [];
        foreach ($planos as $id => $pos) { $celulasAlvo["{$pos['x']}:{$pos['y']}"][] = $id; }
        
        foreach ($celulasAlvo as $posStr => $ids) {
            if (count($ids) > 1) {
                $veiculosEnvolvidos = array_filter($this->veiculos, fn($v) => in_array($v->id, $ids));
                $this->_registrarColisao($veiculosEnvolvidos, "colisão no cruzamento", $tempoResolucaoAcidente, $eventosDoPasso);
                foreach ($ids as $id) unset($planos[$id]);
            }
        }
        
        foreach ($planos as $id => $pos) {
            foreach($this->veiculos as $veiculo) {
                if ($veiculo->id === $id) { 
                    $veiculo->mover($pos['x'], $pos['y']);
                    break;
                }
            }
        }
        
        foreach ($planosDeVirada as $id => $novaDirecao) {
            foreach($this->veiculos as $veiculo) {
                if ($veiculo->id === $id) {
                    $veiculo->direcao = $novaDirecao;
                    switch ($novaDirecao) {
                        case Direcao::NORTE: $veiculo->posicaoX = self::PISTA_NORTE_X; break;
                        case Direcao::SUL: $veiculo->posicaoX = self::PISTA_SUL_X; break;
                        case Direcao::LESTE: $veiculo->posicaoY = self::PISTA_LESTE_Y; break;
                        case Direcao::OESTE: $veiculo->posicaoY = self::PISTA_OESTE_Y; break;
                    }
                    break;
                }
            }
        }
    }

    private function _registrarColisao(array $veiculos, string $motivo, int $tempo, array &$eventosDoPasso): void
    {
        $ids = [];
        foreach ($veiculos as $veiculo) {
            if(!$veiculo->estaColidido()){
                $veiculo->colidir($tempo);
                $ids[] = "#{$veiculo->id}";
            }
        }
        if(!empty($ids)) {
             $eventosDoPasso[] = "COLISÃO em ({$veiculos[0]->posicaoX}, {$veiculos[0]->posicaoY}) envolvendo veículo(s) ".implode(', ', $ids)." por {$motivo}.";
        }
    }
    
    private function _checarPontoDeDecisao(Veiculo $veiculo): bool
    {
        return match ($veiculo->direcao) {
            Direcao::LESTE => $veiculo->posicaoX === self::PISTA_SUL_X - 1 && $veiculo->posicaoY === self::PISTA_LESTE_Y,
            Direcao::OESTE => $veiculo->posicaoX === self::PISTA_NORTE_X + 1 && $veiculo->posicaoY === self::PISTA_OESTE_Y,
            Direcao::SUL => $veiculo->posicaoX === self::PISTA_SUL_X && $veiculo->posicaoY === self::PISTA_LESTE_Y - 1,
            Direcao::NORTE => $veiculo->posicaoX === self::PISTA_NORTE_X && $veiculo->posicaoY === self::PISTA_OESTE_Y + 1,
        };
    }

    private function _obterViradaValida(Direcao $direcaoAtual): Direcao
    {
        return match ($direcaoAtual) {
            Direcao::LESTE => Direcao::NORTE,
            Direcao::OESTE => Direcao::SUL,
            Direcao::SUL => Direcao::OESTE,
            Direcao::NORTE => Direcao::LESTE,
        };
    }
    
    private function _desenharGridNoConsole(): void
    {
        echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
        echo "--- Simulador de Tráfego de Pista Dupla | Passo: {$this->passoAtual} ---\n";
        $statusHorizontal = $this->cruzamento->semaforoHorizontal->estado->value;
        $statusVertical = $this->cruzamento->semaforoVertical->estado->value;
        echo "-------------------------------------------------------------\n";
        echo "Semáforo Horizontal (Eixos < >): " . $statusHorizontal . "\n";
        echo "Semáforo Vertical   (Eixos ^ v): " . $statusVertical . "\n";
        echo "-------------------------------------------------------------\n\n";
        $grid = array_fill(0, self::ALTURA_MUNDO, array_fill(0, self::LARGURA_MUNDO, ' '));
        for ($i = 0; $i < self::LARGURA_MUNDO; $i++) {
            $grid[self::PISTA_LESTE_Y][$i] = '─';
            $grid[self::PISTA_OESTE_Y][$i] = '─';
        }
        for ($i = 0; $i < self::ALTURA_MUNDO; $i++) {
            $grid[$i][self::PISTA_SUL_X] = '│';
            $grid[$i][self::PISTA_NORTE_X] = '│';
        }
        $grid[self::PISTA_LESTE_Y][self::PISTA_SUL_X] = '┼';
        $grid[self::PISTA_LESTE_Y][self::PISTA_NORTE_X] = '┼';
        $grid[self::PISTA_OESTE_Y][self::PISTA_SUL_X] = '┼';
        $grid[self::PISTA_OESTE_Y][self::PISTA_NORTE_X] = '┼';
        foreach ($this->veiculos as $veiculo) {
            if (isset($grid[$veiculo->posicaoY][$veiculo->posicaoX])) {
                $grid[$veiculo->posicaoY][$veiculo->posicaoX] = $veiculo->obterCaractere();
            }
        }
        echo "   ";
        for ($x = 0; $x < self::LARGURA_MUNDO; $x++) echo str_pad($x, 3, ' ', STR_PAD_LEFT);
        echo "\n";
        for ($y = 0; $y < self::ALTURA_MUNDO; $y++) {
            echo str_pad($y, 2, ' ', STR_PAD_LEFT)." ";
            for ($x = 0; $x < self::LARGURA_MUNDO; $x++) {
                echo "[".str_pad($grid[$y][$x], 1)."]";
            }
            echo "\n";
        }
        echo "\n";
        echo "--- Histórico de Eventos (últimos 10) ---\n";
        if (empty($this->logMestre)) {
            echo "Nenhum evento registrado ainda.\n";
        } else {
            $logParaExibir = array_slice($this->logMestre, 0, 10);
            foreach ($logParaExibir as $log) {
                echo "[Passo {$log['passo']}] {$log['evento']}\n";
            }
        }
    }
    private function _obterMapaDePosicoes(): array
    {
        $mapa = [];
        foreach ($this->veiculos as $veiculo) {
             $mapa["{$veiculo->posicaoX}:{$veiculo->posicaoY}"] = true;
        }
        return $mapa;
    }
    private function _gerarVeiculosIniciais(int $numVeiculos): void
    {
        for ($i = 0; $i < $numVeiculos; $i++) {
            $direcaoInt = rand(0, 3);
            $x = 0; $y = 0; $direcao = Direcao::LESTE;
            switch ($direcaoInt) {
                case 0: $direcao = Direcao::LESTE; $x = rand(0, self::LARGURA_MUNDO - 1); $y = self::PISTA_LESTE_Y; break;
                case 1: $direcao = Direcao::OESTE; $x = rand(0, self::LARGURA_MUNDO - 1); $y = self::PISTA_OESTE_Y; break;
                case 2: $direcao = Direcao::SUL; $x = self::PISTA_SUL_X; $y = rand(0, self::ALTURA_MUNDO - 1); break;
                case 3: $direcao = Direcao::NORTE; $x = self::PISTA_NORTE_X; $y = rand(0, self::ALTURA_MUNDO - 1); break;
            }
            if(!isset($this->_obterMapaDePosicoes()["{$x}:{$y}"])) {
                $this->veiculos[] = new Veiculo($x, $y, $direcao);
            }
        }
    }
}