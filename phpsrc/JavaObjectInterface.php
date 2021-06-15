<?php

interface JavaObjectInterface
{
    public function hashCode();
    public function equals(JavaObjectInterface $obj);
}
