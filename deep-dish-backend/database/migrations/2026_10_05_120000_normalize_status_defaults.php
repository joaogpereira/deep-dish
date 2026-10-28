<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Os defaults do banco contradiziam o vocabulário do código (#171).
     *
     * A 2026_04_10_100000 renomeou os status nos REGISTROS ('disponível' virou
     * 'livre', 'pendente' virou 'confirmada'), mas deixou intactos os DEFAULTS das
     * colunas. Então a tabela continuava prometendo um valor que nenhum código
     * entende:
     *
     *   mesa.status        default 'disponível'  (código usa 'livre')
     *   fila.status        default 'pendente'    (código usa 'aberta')
     *   clientemesa.status default 'pendente'    (código usa 'confirmada')
     *
     * Não explode hoje porque todo insert passa pelo código, que sempre manda o
     * status explícito. É uma mina para seeder, fixture ou SQL cru: a linha nasce
     * com um status que nenhuma consulta encontra — mesa 'disponível' nunca é
     * candidata a promoção da fila, por exemplo, porque mesasDisponiveis() procura
     * 'livre'.
     *
     * ALTER COLUMN ... SET DEFAULT em vez de ->change(): o default é a única coisa
     * que muda, não o tipo, e no Postgres isso é instantâneo e não reescreve a
     * tabela. O repo já usa SQL cru de Postgres em migration (índices parciais em
     * 2026_09_30_150000).
     *
     * Os UPDATE abaixo são rede de segurança e no-op em banco saudável — a
     * 2026_04_10 já normalizou os dados. Ficam por idempotência, para o caso de
     * uma linha ter nascido pelo default depois dela.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE mesa ALTER COLUMN status SET DEFAULT 'livre'");
        DB::statement("ALTER TABLE fila ALTER COLUMN status SET DEFAULT 'aberta'");
        DB::statement("ALTER TABLE clientemesa ALTER COLUMN status SET DEFAULT 'confirmada'");

        DB::table('mesa')->where('status', 'disponível')->update(['status' => 'livre']);
        DB::table('fila')->where('status', 'pendente')->update(['status' => 'aberta']);
        DB::table('clientemesa')->where('status', 'pendente')->update(['status' => 'confirmada']);
    }

    /**
     * Volta só os defaults. Os registros NÃO são revertidos de propósito: o
     * vocabulário antigo está morto no código, e reescrevê-los deixaria o banco
     * num estado que a aplicação não sabe ler.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE mesa ALTER COLUMN status SET DEFAULT 'disponível'");
        DB::statement("ALTER TABLE fila ALTER COLUMN status SET DEFAULT 'pendente'");
        DB::statement("ALTER TABLE clientemesa ALTER COLUMN status SET DEFAULT 'pendente'");
    }
};
