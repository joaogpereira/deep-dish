<?php

namespace App\Http\Controllers;

use App\Services\RestauranteService;
use Illuminate\Http\Request;

class RestauranteController extends Controller
{
    public function __construct(private RestauranteService $service) {}

    /**
     * Lista / busca restaurantes por nome e endereço.
     *
     * Filtros aceitos via query string:
     * - q: texto livre (nome, logradouro, bairro, cidade, estado, cep)
     * - cidade
     * - estado
     * - bairro
     * - cep
     * - tipo
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'q',
            'cidade',
            'estado',
            'bairro',
            'cep',
            'tipo',
        ]);

        $restaurantes = $this->service->searchByEndereco($filters);

        return response()->json($restaurantes);
    }

    public function show(string $id)
    {
        $restaurante = $this->service->findById($id);

        if (! $restaurante) {
            return response()->json(['message' => 'Restaurante não encontrado'], 404);
        }

        return response()->json($restaurante);

    }
}
