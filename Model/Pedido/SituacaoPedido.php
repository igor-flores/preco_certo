<?php

namespace Model\Pedido;

enum SituacaoPedido: string
{
    case AguardandoConfirmacao = "aguardando_confirmacao";
    case EmProducao = "em_producao";
    case ProntoRetirada = "pronto_retirada";
    case EmRotaEntrega = "em_rota_entrega";
    case Concluido = "concluido";
    case Cancelado = "cancelado";

    public function rotulo(): string
    {
        return match ($this) {
            self::AguardandoConfirmacao => "Aguardando confirmação do estabelecimento",
            self::EmProducao => "Em produção",
            self::ProntoRetirada => "Pronto para retirada",
            self::EmRotaEntrega => "Em rota de entrega",
            self::Concluido => "Concluído",
            self::Cancelado => "Cancelado",
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Concluido || $this === self::Cancelado;
    }
}
