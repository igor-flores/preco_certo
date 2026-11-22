<?php

namespace Utils;

class Password
{
    private $minLength = 8;
    private $numTests = 5;
    private $minNumTests = 4;

    private $password;
    private $success;

    public function isStronger(): bool
    {
        return $this->success >= $this->minNumTests;
    }

    public function getRating(): bool
    {
        return ($this->success * 100) / $this->numTests;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        $this->check();

        return $this;
    }

    private function check(): self
    {
        $success = 0;
        !$this->hasNumber() ?: $success++;
        !$this->hasUpperCase() ?: $success++;
        !$this->hasLowerCase() ?: $success++;
        !$this->hasSymbol() ?: $success++;
        !$this->hasLength() ?: $success++;

        $this->success = $success;

        return $this;
    }

    private function hasNumber(): bool
    {
        preg_match_all("/[0-9]/", $this->password, $matches, PREG_SET_ORDER, 0);
        
        return count($matches) > 0;
    }

    private function hasUpperCase(): bool
    {
        preg_match_all("/[A-Z]/", $this->password, $matches, PREG_SET_ORDER, 0);
        
        return count($matches) > 0;
    }

    private function hasLowerCase(): bool
    {
        preg_match_all("/[a-z]/", $this->password, $matches, PREG_SET_ORDER, 0);
        
        return count($matches) > 0;
    }

    private function hasSymbol(): bool
    {
        preg_match_all("/[^a-zA-Z0-9]/", $this->password, $matches, PREG_SET_ORDER, 0);
        
        return count($matches) > 0;
    }

    private function hasLength(): bool
    {
        return strlen($this->password) >= $this->minLength;
    }

    public static function getRandom(int $size = 12): string
    {
        $password = "";

        for ($i = 0; $i < $size; $i++) {
            if ($i > 0 && $i % 4 === 0) {
                $password .= Utils::randomSymbol();
                continue;
            }

            $password .= Utils::randomLetter(Utils::randomBoolean());
        }

        return $password;
    }

    public static function passwordVerify(string $password, string $hash): bool
    {

        return password_verify($password, $hash);
    }

    public static function encrypt(string $password): string
    { 
        return password_hash($password, PASSWORD_BCRYPT, array("cost" => 10));
    }
}