<?php

namespace App\Infraestrutura;

use App\Enums\Direcao;
use App\Enums\EstadoSemaforo;

class Cruzamento
{
    public readonly Semaforo $semaforoHorizontal;
    public readonly Semaforo $semaforoVertical;

    public function __construct(
        public readonly int $posicaoX,
        public readonly int $posicaoY,
        int $tempoVerde,
        int $tempoVermelho,
        int $atrasoVerde // Novo: recebe o tempo de atraso
    ) {
        // Repassa os tempos, incluindo o de atraso, para os objetos Semaforo
        $this->semaforoHorizontal = new Semaforo(EstadoSemaforo::VERDE, $tempoVerde, $tempoVermelho, $atrasoVerde);
        $this->semaforoVertical = new Semaforo(EstadoSemaforo::VERMELHO, $tempoVerde, $tempoVermelho, $atrasoVerde);
    }

    public function atualizarSemaforos(): void
    {
        $this->semaforoHorizontal->atualizar();
        $this->semaforoVertical->atualizar();
    }

    public function podePassar(Direcao $direcao): bool
    {
        return match ($direcao) {
            Direcao::LESTE, Direcao::OESTE => $this->semaforoHorizontal->podePassar(),
            Direcao::NORTE, Direcao::SUL => $this->semaforoVertical->podePassar(),
        };
    }
}