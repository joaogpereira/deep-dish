-- Roda uma unica vez, quando o volume postgres_test_data esta vazio (primeira
-- subida, ou depois de 'docker compose down -v'). O deepdish_test ja nasce
-- pelo POSTGRES_DB do docker-compose.yml; aqui so falta o banco de dev.
--
-- Separado do deepdish_test de proposito: o RefreshDatabase da suite apaga o
-- deepdish_test a cada rodada, e o cenario de desenvolvimento nao pode ir junto.
CREATE DATABASE deepdish_dev;
