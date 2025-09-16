<?php

namespace App\Simulacao;

use App\Entidades\Veiculo;
use App\Enums\Direcao;
use App\Enums\StatusVeiculo;
use App\Infraestrutura\Cruzamento;

class Simulador
{
    private const LARGURA_MUNDO = 21;
    private const ALTURA_MUNDO = 21;
    private const AVENIDA_HORIZONTAL_Y = 10;
    private const AVENIDA_VERTICAL_X = 10;

    /** @var Veiculo[] */
    private array $veiculos = [];
    private Cruzamento $cruzamento;
    
    // --- MODIFICAÇÃO: Introduzindo o log mestre persistente ---
    private array $logMestre = [];
    
    private int $passoAtual = 0;

    public function __construct(int $numVeiculosIniciais)
    {
        $this->cruzamento = new Cruzamento(self::AVENIDA_VERTICAL_X, self::AVENIDA_HORIZONTAL_Y);
        $this->_gerarVeiculosIniciais($numVeiculosIniciais);
    }

    public function rodar(int $totalPassos, int $tempoResolucaoAcidente): void
    {
        for ($i = 1; $i <= $totalPassos; $i++) {
            $this->passoAtual = $i;
            $this->_executarPasso($tempoResolucaoAcidente);
            $this->_desenharGridNoConsole();
            usleep(200000); // 0.2 segundos de pausa
        }
    }

    private function _executarPasso(int $tempoResolucaoAcidente): void
    {
        // --- MODIFICAÇÃO: Usamos um array local para os eventos do passo atual ---
        $eventosDoPasso = [];

        $this->cruzamento->atualizarSemaforos();
        $this->_atualizarStatusDeColisoes($eventosDoPasso);

        $planosDeMovimento = $this->_planejarMovimentos($eventosDoPasso);
        $this->_resolverConflitosEMover($planosDeMovimento, $tempoResolucaoAcidente, $eventosDoPasso);

        // --- MODIFICAÇÃO: Adiciona os eventos do passo ao início do log mestre ---
        $novosLogsFormatados = [];
        foreach ($eventosDoPasso as $evento) {
            $novosLogsFormatados[] = ['passo' => $this->passoAtual, 'evento' => $evento];
        }
        // array_merge coloca os novos eventos antes dos antigos, mantendo a ordem interna dos novos
        $this->logMestre = array_merge($novosLogsFormatados, $this->logMestre);
    }

    // --- MODIFICAÇÃO: O método agora aceita o array de eventos como parâmetro ---
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
    
    // --- MODIFICAÇÃO: O método agora aceita o array de eventos como parâmetro ---
    private function _planejarMovimentos(array &$eventosDoPasso): array
    {
        $planos = [];
        $posicoesOcupadas = $this->_obterMapaDePosicoes();

        foreach ($this->veiculos as $veiculo) {
            if ($veiculo->estaColidido()) continue;

            if (mt_rand(1, 1000) === 1) { // 0.1% chance de acidente espontâneo
                $this->_registrarColisao([$veiculo], "acidente espontâneo", 20, $eventosDoPasso);
                continue;
            }

            $proximaPos = $veiculo->obterProximaPosicao();

            if ($proximaPos['x'] < 0) $proximaPos['x'] = self::LARGURA_MUNDO - 1;
            if ($proximaPos['x'] >= self::LARGURA_MUNDO) $proximaPos['x'] = 0;
            if ($proximaPos['y'] < 0) $proximaPos['y'] = self::ALTURA_MUNDO - 1;
            if ($proximaPos['y'] >= self::ALTURA_MUNDO) $proximaPos['y'] = 0;

            if ($proximaPos['x'] === $this->cruzamento->posicaoX && $proximaPos['y'] === $this->cruzamento->posicaoY) {
                if (!$this->cruzamento->podePassar($veiculo->direcao)) {
                    if (mt_rand(1, 100) <= 5) {
                        $eventosDoPasso[] = "Veículo #{$veiculo->id} furou o sinal vermelho!";
                    } else {
                        continue;
                    }
                }
            }
            
            $proximaPosStr = "{$proximaPos['x']}:{$proximaPos['y']}";
            if(isset($posicoesOcupadas[$proximaPosStr])) {
                continue;
            }

            $planos[$veiculo->id] = $proximaPos;
        }
        return $planos;
    }
    
    // --- MODIFICAÇÃO: O método agora aceita o array de eventos como parâmetro ---
    private function _resolverConflitosEMover(array $planos, int $tempoResolucaoAcidente, array &$eventosDoPasso): void
    {
        $celulasAlvo = [];
        foreach ($planos as $id => $pos) {
            $celulasAlvo["{$pos['x']}:{$pos['y']}"][] = $id;
        }

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
    }
    
    // --- MODIFICAÇÃO: O método agora aceita o array de eventos como parâmetro ---
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

    // --- MODIFICAÇÃO PRINCIPAL: A lógica de desenho agora usa o log mestre ---
    private function _desenharGridNoConsole(): void
    {
        echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
        echo "--- Simulador de Tráfego | Passo: {$this->passoAtual} ---\n\n";

        $grid = array_fill(0, self::ALTURA_MUNDO, array_fill(0, self::LARGURA_MUNDO, ' '));
        for ($i = 0; $i < self::LARGURA_MUNDO; $i++) $grid[self::AVENIDA_HORIZONTAL_Y][$i] = '-';
        for ($i = 0; $i < self::ALTURA_MUNDO; $i++) $grid[$i][self::AVENIDA_VERTICAL_X] = '|';
        $grid[$this->cruzamento->posicaoY][$this->cruzamento->posicaoX] = '+';
        foreach ($this->veiculos as $veiculo) {
            $grid[$veiculo->posicaoY][$veiculo->posicaoX] = $veiculo->obterCaractere();
        }

        echo "   ";
        for ($x = 0; $x < self::LARGURA_MUNDO; $x++) echo str_pad($x, 3, ' ', STR_PAD_LEFT);
        echo "\n";
        for ($y = 0; $y < self::ALTURA_MUNDO; $y++) {
            echo str_pad($y, 2, ' ', STR_PAD_LEFT)." ";
            for ($x = 0; $x < self::LARGURA_MUNDO; $x++) {
                echo "[{$grid[$y][$x]}]";
            }
            echo "\n";
        }
        echo "\n";

        echo "--- Histórico de Eventos (últimos 10) ---\n";
        if (empty($this->logMestre)) {
            echo "Nenhum evento registrado ainda.\n";
        } else {
            // Pega apenas os 10 eventos mais recentes para não poluir a tela
            $logParaExibir = array_slice($this->logMestre, 0, 10);
            foreach ($logParaExibir as $log) {
                echo "[Passo {$log['passo']}] {$log['evento']}\n";
            }
        }
    }

    // Métodos _obterMapaDePosicoes e _gerarVeiculosIniciais permanecem inalterados
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
            $direcao = (rand(0, 1) === 0) ? Direcao::LESTE : Direcao::OESTE;
            if($i % 2 === 0) {
                 $direcao = (rand(0, 1) === 0) ? Direcao::NORTE : Direcao::SUL;
            }
           
            $x = ($direcao === Direcao::LESTE || $direcao === Direcao::OESTE) ? rand(0, self::LARGURA_MUNDO-1) : self::AVENIDA_VERTICAL_X;
            $y = ($direcao === Direcao::NORTE || $direcao === Direcao::SUL) ? rand(0, self::ALTURA_MUNDO-1) : self::AVENIDA_HORIZONTAL_Y;

            $this->veiculos[] = new Veiculo($x, $y, $direcao);
        }
    }
}