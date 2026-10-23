<?php

namespace App\Http;

use Illuminate\Http\Request as BaseRequest;

class Request extends BaseRequest
{
    /**
     * En un despliegue en subdirectorio, al quitar el slash final de /scl/
     * Symfony deja de detectar /scl como base y el path info queda como
     * "/scl" en lugar de "/". Normalizamos ese caso para que Laravel
     * resuelva correctamente la ruta raiz.
     */
    public function getPathInfo(): string
    {
        $pathInfo = parent::getPathInfo();

        $scriptName = str_replace('\\', '/', (string) $this->server->get('SCRIPT_NAME', ''));
        $basePath = rtrim(dirname($scriptName), '/');

        if ($basePath !== '' && $basePath !== '.'
            && ($pathInfo === $basePath || str_starts_with($pathInfo, $basePath.'/'))) {
            $pathInfo = substr($pathInfo, strlen($basePath));
        }

        return $pathInfo === '' ? '/' : $pathInfo;
    }
}
