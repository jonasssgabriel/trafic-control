<?php

namespace App\Infraestrutura;

use App\Enums\EstadoSemaforo;

class Semaforo
{
    private int $contadorTempo;
    private int $atrasoVerdeRestante = 0; // Novo: contador para o atraso

    public function __construct(
        public EstadoSemaforo $estado,
        private readonly int $tempoVerde,
        private readonly int $tempoVermelho,
        private readonly int $atrasoAposVerde // Novo: recebe o tempo de atraso
    ) {
        $this->reiniciarContador();
    }

    public function atualizar(): void
    {
        // Decrementa o contador de atraso primeiro, se estiver ativo
        if ($this->atrasoVerdeRestante > 0) {
            $this->atrasoVerdeRestante--;
        }

        $this->contadorTempo--;
        if ($this->contadorTempo <= 0) {
            $this->estado = ($this->estado === EstadoSemaforo::VERDE)
                ? EstadoSemaforo::VERMELHO
                : EstadoSemaforo::VERDE;
            
            // Se acabou de mudar para VERDE, ativa o contador de atraso
            if ($this->estado === EstadoSemaforo::VERDE) {
                $this->atrasoVerdeRestante = $this->atrasoAposVerde;
            }

            $this->reiniciarContador();
        }
    }

    public function podePassar(): bool
    {
        // Agora só pode passar se estiver VERDE E o atraso tiver terminado
        return $this->estado === EstadoSemaforo::VERDE && $this->atrasoVerdeRestante === 0;
    }

    // Novo: Método para a exibição visual no console
    public function obterEstadoVisual(): string
    {
        if ($this->estado === EstadoSemaforo::VERDE && $this->atrasoVerdeRestante > 0) {
            return "VERDE (ESPERA {$this->atrasoVerdeRestante})";
        }
        return $this->estado->value;
    }

    private function reiniciarContador(): void
    {
        $this->contadorTempo = ($this->estado === EstadoSemaforo::VERDE)
            ? $this->tempoVerde
            : $this->tempoVermelho;
    }
}