<?php

namespace App\Entity;

use App\Repository\CommandRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandRepository::class)]
#[ORM\Table(name: 'command')]
class Command
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'script_name', type: 'string', nullable: true)]
    private ?string $scriptName = null;

    #[ORM\Column(name: 'recurrence', type: 'string', nullable: true)]
    private ?string $recurrence = null;

    #[ORM\Column(name: 'interval', type: 'integer', nullable: true)]
    private ?int $interval = null;

    #[ORM\Column(name: 'attempt_max', type: 'integer', nullable: true, options: ['default' => 5])]
    private ?int $attemptMax = 5;

    #[ORM\Column(name: 'last_execution_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastExecutionDate = null;

    #[ORM\Column(name: 'next_execution_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nextExecutionDate = null;

    #[ORM\Column(name: 'last_status', type: 'string', nullable: true)]
    private ?string $lastStatus = null;

    #[ORM\Column(name: 'start_time', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(name: 'end_time', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(name: 'status_scheduler', type: 'boolean', nullable: true)]
    private ?bool $statusScheduler = null;

    #[ORM\Column(name: 'status_send_email', type: 'boolean', nullable: true)]
    private ?bool $statusSendEmail = null;

    #[ORM\Column(name: 'manual_execution_date', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $manualExecutionDate = null;

    #[ORM\OneToMany(targetEntity: CommandExecution::class, mappedBy: 'command', orphanRemoval: true)]
    private Collection $executions;

    #[ORM\Column(name: 'active', type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    public function __construct()
    {
        $this->executions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getScriptName(): ?string
    {
        return $this->scriptName;
    }

    public function setScriptName(string $scriptName): static
    {
        $this->scriptName = $scriptName;
        return $this;
    }

    public function getRecurrence(): ?string
    {
        return $this->recurrence;
    }

    public function setRecurrence(?string $recurrence): self
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function setInterval(?int $interval): self
    {
        $this->interval = $interval;
        return $this;
    }

    public function getAttemptMax(): ?int
    {
        return $this->attemptMax;
    }

    public function setAttemptMax(int $attemptMax): self
    {
        $this->attemptMax = $attemptMax;
        return $this;
    }

    public function getLastExecutionDate(): ?\DateTimeInterface
    {
        return $this->lastExecutionDate;
    }

    public function setLastExecutionDate(?\DateTimeInterface $lastExecutionDate): self
    {
        $this->lastExecutionDate = $lastExecutionDate;
        return $this;
    }

    public function getNextExecutionDate(): ?\DateTimeInterface
    {
        return $this->nextExecutionDate;
    }

    public function setNextExecutionDate(?\DateTimeInterface $nextExecutionDate): self
    {
        $this->nextExecutionDate = $nextExecutionDate;
        return $this;
    }

    public function getLastStatus(): ?string
    {
        return $this->lastStatus;
    }

    public function setLastStatus(?string $lastStatus): self
    {
        $this->lastStatus = $lastStatus;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getStatusScheduler(): bool
    {
        return $this->statusScheduler ?? false;
    }

    public function setStatusScheduler(?bool $statusScheduler): self
    {
        $this->statusScheduler = $statusScheduler;
        return $this;
    }

    public function getStatusSendEmail(): bool
    {
        return $this->statusSendEmail ?? false;
    }

    public function setStatusSendEmail(?bool $statusSendEmail): self
    {
        $this->statusSendEmail = $statusSendEmail;
        return $this;
    }

    public function getManualExecutionDate(): ?\DateTimeInterface
    {
        return $this->manualExecutionDate;
    }

    public function setManualExecutionDate(?\DateTimeInterface $manualExecutionDate): self
    {
        $this->manualExecutionDate = $manualExecutionDate;
        return $this;
    }

    /**
     * @return Collection<int, CommandExecution>
     */
    public function getExecutions(): Collection
    {
        return $this->executions;
    }

    public function addExecution(CommandExecution $execution): static
    {
        if (!$this->executions->contains($execution)) {
            $this->executions->add($execution);
            $execution->setCommand($this);
        }

        return $this;
    }

    public function removeExecution(CommandExecution $execution): static
    {
        if ($this->executions->removeElement($execution)) {
            // set the owning side to null (unless already changed)
            if ($execution->getCommand() === $this) {
                $execution->setCommand(null);
            }
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }
}
