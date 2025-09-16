<?php

namespace App\Infraestrutura;

use App\Enums\EstadoSemaforo;

class Semaforo
{
    private int $contadorTempo;

    public function __construct(
        public EstadoSemaforo $estado,
        private readonly int $tempoVerde,
        private readonly int $tempoVermelho
    ) {
        $this->reiniciarContador();
    }

    public function atualizar(): void
    {
        $this->contadorTempo--;
        if ($this->contadorTempo <= 0) {
            $this->estado = ($this->estado === EstadoSemaforo::VERDE)
                ? EstadoSemaforo::VERMELHO
                : EstadoSemaforo::VERDE;
            $this->reiniciarContador();
        }
    }

    public function podePassar(): bool
    {
        return $this->estado === EstadoSemaforo::VERDE;
    }

    private function reiniciarContador(): void
    {
        $this->contadorTempo = ($this->estado === EstadoSemaforo::VERDE)
            ? $this->tempoVerde
            : $this->tempoVermelho;
    }
}