<?php

namespace App\Entidades;

use App\Enums\Direcao;
use App\Enums\StatusVeiculo;

class Veiculo
{
    private static int $proximoId = 1;
    public readonly int $id;
    public StatusVeiculo $status = StatusVeiculo::ATIVO;
    public int $passosColididoRestantes = 0;

    public function __construct(
        public int $posicaoX,
        public int $posicaoY,
        public Direcao $direcao
    ) {
        $this->id = self::$proximoId++;
    }

    public function obterCaractere(): string
    {
        if ($this->status === StatusVeiculo::COLIDIDO) {
            return 'C';
        }
        return match ($this->direcao) {
            Direcao::NORTE => '^',
            Direcao::SUL => 'v',
            Direcao::LESTE => '>',
            Direcao::OESTE => '<',
        };
    }

    public function obterProximaPosicao(): array
    {
        return match ($this->direcao) {
            Direcao::NORTE => ['x' => $this->posicaoX, 'y' => $this->posicaoY - 1],
            Direcao::SUL => ['x' => $this->posicaoX, 'y' => $this->posicaoY + 1],
            Direcao::LESTE => ['x' => $this->posicaoX + 1, 'y' => $this->posicaoY],
            Direcao::OESTE => ['x' => $this->posicaoX - 1, 'y' => $this->posicaoY],
        };
    }

    public function mover(int $novaPosX, int $novaPosY): void
    {
        $this->posicaoX = $novaPosX;
        $this->posicaoY = $novaPosY;
    }

    public function colidir(int $tempoDeResolucao): void
    {
        $this->status = StatusVeiculo::COLIDIDO;
        $this->passosColididoRestantes = $tempoDeResolucao;
    }

    public function estaColidido(): bool
    {
        return $this->status === StatusVeiculo::COLIDIDO;
    }

    /**
     * Decrementa o tempo de colisão. Retorna true se o tempo acabou.
     */
    public function tickColisao(): bool
    {
        if ($this->estaColidido() && $this->passosColididoRestantes > 0) {
            $this->passosColididoRestantes--;
            if ($this->passosColididoRestantes === 0) {
                return true; // Acidente resolvido neste tick
            }
        }
        return false;
    }
}