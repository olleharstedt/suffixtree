<?php

interface JavaObjectInterface
{
    public function hashCode(): int;
    public function equals(JavaObjectInterface $obj): bool;
}
