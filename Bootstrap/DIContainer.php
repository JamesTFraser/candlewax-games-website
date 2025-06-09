<?php

namespace CandlewaxGames\Bootstrap;

class DIContainer
{
    private static DependencyInjector $injector;

    public function getDI(): DependencyInjector
    {
        if (!isset(self::$injector)) {
            self::$injector = new DependencyInjector();
        }

        return self::$injector;
    }
}
