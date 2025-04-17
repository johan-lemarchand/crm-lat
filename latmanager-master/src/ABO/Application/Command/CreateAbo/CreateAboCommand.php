<?php

namespace App\ABO\Application\Command\CreateAbo;

readonly class CreateAboCommand
{
    /**
     * @param string $pcvnum
     * @param string $user
     * @param array $lignes
     * @param array $automateE
     * @param array $automateAB
     * @param array $automateAF
     * @param array $automateAL
     * @param array $automateAA
     * @param array $automateAE
     * @param int $memoId
     */
    public function __construct(
        public string $pcvnum,
        public string $user,
        public array  $lignes,
        public array  $automateE,
        public array  $automateAB,
        public array  $automateAF,
        public array  $automateAL,
        public array  $automateAA,
        public array  $automateAE,
        public int $memoId,
    ) {
    }
    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPcvnum(): string
    {
        return $this->pcvnum;
    }
} 