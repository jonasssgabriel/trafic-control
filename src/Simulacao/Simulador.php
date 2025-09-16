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
    private const PISTA_OESTE_Y = 10;
    private const PISTA_LESTE_Y = 11;
    private const PISTA_SUL_X = 10;
    private const PISTA_NORTE_X = 11;
    
    // --- MELHORIA: Motivos variados para colisões espontâneas ---
    private const MOTIVOS_COLISAO_ESPONTANEA = [
        'falta de combustível',
        'pneu furado',
        'motor quebrado',
        'incêndio no veículo',
        'infarto do motorista',
        'perdeu o controle',
    ];

    /** @var Veiculo[] */
    private array $veiculos = [];
    private Cruzamento $cruzamento;
    private array $logMestre = [];
    private int $passoAtual = 0;

    public function __construct(
        int $numVeiculosIniciais,
        int $tempoVerde,
        int $tempoVermelho,
        int $atrasoVerde,
        private readonly float $taxaFuroSemaforo,
        private readonly float $taxaColisaoEspontanea
    ) {
        $this->cruzamento = new Cruzamento(self::PISTA_SUL_X, self::PISTA_OESTE_Y, $tempoVerde, $tempoVermelho, $atrasoVerde);
        $this->_gerarVeiculosIniciais($numVeiculosIniciais);
    }

    public function rodar(int $totalPassos, int $tempoResolucaoAcidente, int $velocidadeSimulacao): void
    {
        for ($i = 1; $i <= $totalPassos; $i++) {
            $this->passoAtual = $i;
            $this->_executarPasso($tempoResolucaoAcidente);
            $this->_desenharGridNoConsole();
            usleep($velocidadeSimulacao);
        }
    }

    public function _executarPasso(int $tempoResolucaoAcidente): void
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

    private function _planejarMovimentos(array &$eventosDoPasso, array &$planosDeVirada): array
    {
        $planosDeMovimento = [];
        $posicoesOcupadas = $this->_obterMapaDePosicoes();
        foreach ($this->veiculos as $veiculo) {
            if ($veiculo->estaColidido()) continue;

            // --- MELHORIA: Usa um motivo aleatório para a colisão espontânea ---
            if ((mt_rand() / mt_getrandmax()) < $this->taxaColisaoEspontanea) {
                $motivo = self::MOTIVOS_COLISAO_ESPONTANEA[array_rand(self::MOTIVOS_COLISAO_ESPONTANEA)];
                $this->_registrarColisao([$veiculo], $motivo, 20, $eventosDoPasso);
                continue;
            }
            $proximaPos = $veiculo->obterProximaPosicao();
            if ($proximaPos['x'] < 0) $proximaPos['x'] = self::LARGURA_MUNDO - 1;
            if ($proximaPos['x'] >= self::LARGURA_MUNDO) $proximaPos['x'] = 0;
            if ($proximaPos['y'] < 0) $proximaPos['y'] = self::ALTURA_MUNDO - 1;
            if ($proximaPos['y'] >= self::ALTURA_MUNDO) $proximaPos['y'] = 0;
            $podeAvancar = true;
            $estaDentroDoCruzamento = ($veiculo->posicaoX >= self::PISTA_SUL_X && $veiculo->posicaoX <= self::PISTA_NORTE_X) && ($veiculo->posicaoY >= self::PISTA_OESTE_Y && $veiculo->posicaoY <= self::PISTA_LESTE_Y);
            if (!$estaDentroDoCruzamento) {
                $vaiEntrarNoCruzamento = ($proximaPos['x'] >= self::PISTA_SUL_X && $proximaPos['x'] <= self::PISTA_NORTE_X) && ($proximaPos['y'] >= self::PISTA_OESTE_Y && $proximaPos['y'] <= self::PISTA_LESTE_Y);
                if ($vaiEntrarNoCruzamento) {
                    $temSinalVerde = $this->cruzamento->podePassar($veiculo->direcao);
                    $vaiFurarSinal = !$temSinalVerde && ((mt_rand() / mt_getrandmax()) < $this->taxaFuroSemaforo);
                    if ($temSinalVerde || $vaiFurarSinal) {
                        if ($vaiFurarSinal) {
                            $eventosDoPasso[] = "Veículo #{$veiculo->id} furou o sinal vermelho!";
                        }
                        $pontoDeDecisao = $this->_checarPontoDeDecisao($veiculo);
                        if ($pontoDeDecisao && mt_rand(1, 100) <= 30) {
                            $novaDirecao = $this->_obterViradaValida($veiculo->direcao);
                            $planosDeVirada[$veiculo->id] = $novaDirecao;
                            $eventosDoPasso[] = "Veículo #{$veiculo->id} planeja virar para {$novaDirecao->value}.";
                        }
                    } else {
                        $podeAvancar = false;
                    }
                }
            }
            if (!$podeAvancar) { continue; }
            $proximaPosStr = "{$proximaPos['x']}:{$proximaPos['y']}";
            if (isset($posicoesOcupadas[$proximaPosStr])) { continue; }
            $planosDeMovimento[$veiculo->id] = $proximaPos;
        }
        return $planosDeMovimento;
    }

    // --- CORREÇÃO DO BUG: Lógica de resolução de conflitos totalmente refeita ---
    private function _resolverConflitosEMover(array $planosDeMovimento, int $tempoResolucaoAcidente, array &$eventosDoPasso, array $planosDeVirada): void
    {
        $celulasAlvoFinais = [];
        $posicoesEstaticas = [];

        // 1. Mapeia as posições finais de todos os veículos (parados e em movimento)
        foreach ($this->veiculos as $veiculo) {
            if (isset($planosDeMovimento[$veiculo->id])) { // Veículo está tentando se mover
                $posFinal = $planosDeMovimento[$veiculo->id];
                // Se o veículo planeja virar, sua célula final é a da pista de destino
                if (isset($planosDeVirada[$veiculo->id])) {
                    $novaDirecao = $planosDeVirada[$veiculo->id];
                    switch ($novaDirecao) {
                        case Direcao::NORTE: $posFinal['x'] = self::PISTA_NORTE_X; break;
                        case Direcao::SUL:   $posFinal['x'] = self::PISTA_SUL_X;   break;
                        case Direcao::LESTE: $posFinal['y'] = self::PISTA_LESTE_Y; break;
                        case Direcao::OESTE: $posFinal['y'] = self::PISTA_OESTE_Y; break;
                    }
                }
                $celulasAlvoFinais["{$posFinal['x']}:{$posFinal['y']}"][] = $veiculo;
            } else { // Veículo está parado
                $posicoesEstaticas["{$veiculo->posicaoX}:{$veiculo->posicaoY}"][] = $veiculo;
            }
        }

        // 2. Detecta conflitos
        $idsComConflito = [];
        foreach ($celulasAlvoFinais as $posStr => $veiculos) {
            $veiculosNaMesmaCelula = $veiculos;
            // Verifica se um veículo em movimento quer entrar numa célula já estaticamente ocupada
            if (isset($posicoesEstaticas[$posStr])) {
                $veiculosNaMesmaCelula = array_merge($veiculos, $posicoesEstaticas[$posStr]);
            }

            if (count($veiculosNaMesmaCelula) > 1) { // Conflito detectado!
                $this->_registrarColisao($veiculosNaMesmaCelula, "conflito no cruzamento", $tempoResolucaoAcidente, $eventosDoPasso);
                foreach ($veiculosNaMesmaCelula as $v) {
                    $idsComConflito[$v->id] = true;
                }
            }
        }

        // 3. Executa os movimentos e viradas válidos (sem conflitos)
        foreach ($this->veiculos as $veiculo) {
            if (isset($idsComConflito[$veiculo->id])) continue; // Veículo envolvido em conflito não se move

            if (isset($planosDeMovimento[$veiculo->id])) {
                $pos = $planosDeMovimento[$veiculo->id];
                $veiculo->mover($pos['x'], $pos['y']);

                if (isset($planosDeVirada[$veiculo->id])) {
                    $novaDirecao = $planosDeVirada[$veiculo->id];
                    $veiculo->direcao = $novaDirecao;
                    // Corrige a posição para a pista correta APÓS o movimento
                    switch ($novaDirecao) {
                        case Direcao::NORTE: $veiculo->posicaoX = self::PISTA_NORTE_X; break;
                        case Direcao::SUL:   $veiculo->posicaoX = self::PISTA_SUL_X;   break;
                        case Direcao::LESTE: $veiculo->posicaoY = self::PISTA_LESTE_Y; break;
                        case Direcao::OESTE: $veiculo->posicaoY = self::PISTA_OESTE_Y; break;
                    }
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
            Direcao::SUL => $veiculo->posicaoX === self::PISTA_SUL_X && $veiculo->posicaoY === self::PISTA_OESTE_Y - 1,
            Direcao::NORTE => $veiculo->posicaoX === self::PISTA_NORTE_X && $veiculo->posicaoY === self::PISTA_LESTE_Y + 1,
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
    public function _desenharGridNoConsole(): void
    {
        echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
        echo "--- Simulador de Tráfego de Pista Dupla | Passo: {$this->passoAtual} ---\n";
        $statusHorizontal = $this->cruzamento->semaforoHorizontal->obterEstadoVisual();
        $statusVertical = $this->cruzamento->semaforoVertical->obterEstadoVisual();
        echo "-------------------------------------------------------------\n";
        echo "Semáforo Horizontal (Eixos < >): " . $statusHorizontal . "\n";
        echo "Semáforo Vertical   (Eixos ^ v): " . $statusVertical . "\n";
        echo "-------------------------------------------------------------\n\n";
        $grid = array_fill(0, self::ALTURA_MUNDO, array_fill(0, self::LARGURA_MUNDO, ' '));
        for ($i = 0; $i < self::LARGURA_MUNDO; $i++) {
            $grid[self::PISTA_OESTE_Y][$i] = '─';
            $grid[self::PISTA_LESTE_Y][$i] = '─';
        }
        for ($i = 0; $i < self::ALTURA_MUNDO; $i++) {
            $grid[$i][self::PISTA_SUL_X] = '│';
            $grid[$i][self::PISTA_NORTE_X] = '│';
        }
        $grid[self::PISTA_OESTE_Y][self::PISTA_SUL_X] = '┼';
        $grid[self::PISTA_OESTE_Y][self::PISTA_NORTE_X] = '┼';
        $grid[self::PISTA_LESTE_Y][self::PISTA_SUL_X] = '┼';
        $grid[self::PISTA_LESTE_Y][self::PISTA_NORTE_X] = '┼';
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
            for ($x = 0; $x < self::LARGURA_MUNDO; $x++) { echo "[".str_pad($grid[$y][$x], 1)."]"; }
            echo "\n";
        }
        echo "\n";
        echo "--- Histórico de Eventos (últimos 10) ---\n";
        if (empty($this->logMestre)) { echo "Nenhum evento registrado ainda.\n"; } else {
            $logParaExibir = array_slice($this->logMestre, 0, 10);
            foreach ($logParaExibir as $log) { echo "[Passo {$log['passo']}] {$log['evento']}\n"; }
        }
    }
    private function _obterMapaDePosicoes(): array
    {
        $mapa = [];
        foreach ($this->veiculos as $veiculo) { $mapa["{$veiculo->posicaoX}:{$veiculo->posicaoY}"] = true; }
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