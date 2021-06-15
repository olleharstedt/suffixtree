<?php

/**
 * A sentinel character which can be used to produce explicit leaves for all
 * suffixes. The sentinel just has to be appended to the list before handing
 * it to the suffix tree. For the sentinel equality and object identity are
 * the same!
 */
class Sentinel implements JavaObjectInterface
{
    /** The hash value used. */
    private $hash;

    public function __construct() {
        $this->hash = (int) rand(0, 2147483647);
    }

    public function hashCode(): int {
        return $hash;
    }

    public function equals(object $obj): bool {
        return $obj == $this;
    }

    public function toString(): string {
        return "$";
    }
}
