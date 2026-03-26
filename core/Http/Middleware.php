<?php

namespace Framework\Core\Http;

interface Middleware {
    /**
     * @param Request $request
     * @param callable $next A function that returns a Response
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
