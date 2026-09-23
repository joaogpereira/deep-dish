<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservas:expirar')->everyFiveMinutes();

// A cada minuto, não a cada cinco: com tolerância de 15 min, rodar de cinco em
// cinco deixaria a mesa parada até 20 min antes de ir para o próximo.
Schedule::command('fila:expirar-chamados')->everyMinute();

// Rede de segurança do ponto único de promoção: os caminhos que liberam lugar
// já chamam a fila na hora, e este comando pega o resto (entrar na fila com
// mesa vaga, janela de reserva vencida no relógio).
Schedule::command('fila:promover')->everyMinute();
