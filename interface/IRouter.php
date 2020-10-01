<?php

interface IRouter
{
    public function use($func);

    public function resolve();

    public function next();
}
