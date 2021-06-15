<?php

class PhpToken implements JavaObjectInterface
{
    public $tokenCode;
    public $line;
    public $file;
    public $tokenName;
    public $content;

    public function __construct(
        int $tokenCode,
        string $tokenName,
        int $line,
        string $file,
        string $content
    ) {
        $this->tokenCode = $tokenCode;
        $this->tokenName = $tokenName;
        $this->line = $line;
        $this->content = $content;
        $this->file = $file;
    }

    /**
     * @return int
     */
    public function hashCode() {
        return $this->content->hashCode();
        //return $tokenCode;
    }

    /**
     * @return boolean
     */
    public function equals(JavaObjectInterface $token) {
        return $token->hashCode() == $this->hashCode();
    }

    /**
     * @return string
     */
    public function toString() {
        return $tokenName;
    }
}
