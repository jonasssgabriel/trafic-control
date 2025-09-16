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
        public readonly int $posicaoY
    ) {
        $this->semaforoHorizontal = new Semaforo(EstadoSemaforo::VERDE, 15, 15);
        $this->semaforoVertical = new Semaforo(EstadoSemaforo::VERMELHO, 15, 15);
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