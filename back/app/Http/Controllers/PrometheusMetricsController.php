<?php

namespace App\Http\Controllers;

use App\Services\PrometheusExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrometheusMetricsController extends Controller
{
    public function __invoke(Request $request, PrometheusExporter $exporter): Response
    {
        return response($exporter->render($request->user()), 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
