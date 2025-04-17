<?php

namespace App\Service;

use App\Entity\Command;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class EmailService
{
    private const MAX_RETRIES = 5;
    private const RETRY_DELAY = 3;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly EmailTemplateService $templateService,
        private readonly string $defaultFromEmail = 'srvlatbdd-test-jojo@latitudegps.com',
        private readonly string $defaultFromName = 'Project Manager (noreply)',
        private readonly string $defaultToEmail = 'informatique@latitudegps.com',
    ) {
    }

    public function sendEmail(
        string $subject,
        string $content,
        ?string $toEmail = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        bool $isHtml = false,
    ): void {
        $attempt = 0;
        $lastError = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $email = (new Email())
                    ->from(new Address($fromEmail ?? $this->defaultFromEmail, $fromName ?? $this->defaultFromName))
                    ->to($toEmail ?? $this->defaultToEmail)
                    ->subject($subject);

                if ($isHtml) {
                    $email->html($content);
                } else {
                    $email->text($content);
                }

                $this->mailer->send($email);
                $this->logger->info('Email envoyé avec succès', [
                    'subject' => $subject,
                    'to' => $toEmail ?? $this->defaultToEmail
                ]);
                return;
            } catch (TransportExceptionInterface $e) {
                $lastError = $e;
                ++$attempt;
                
                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY);
                }
            } catch (\Exception $e) {
                $this->logger->error('Erreur critique lors de l\'envoi du mail', [
                    'error' => $e->getMessage()
                ]);
                return;
            }
        }

        if ($lastError) {
            $this->logger->error('Échec de l\'envoi du mail après ' . self::MAX_RETRIES . ' tentatives', [
                'error' => $lastError->getMessage()
            ]);
        }
    }

    public function sendCommandSchedulerAlert(string $commandName, string $lastExecution, int $attemptMax, Command $command): void
    {
        if (!$command->getStatusSendEmail()) {
            return;
        }

        $subject = "ALERTE - Commande en erreur : {$commandName}";
        $content = "Bonjour,\n\n";
        $content .= "La commande {$commandName} est en erreur.\n\n";
        $content .= "Détails :\n";
        $content .= "======================\n";
        $content .= "Dernière exécution : {$lastExecution}\n";
        $content .= "Nombre maximum de tentatives ({$attemptMax} minutes) dépassé.\n\n";
        $content .= "Merci de vérifier l'état de la commande.";

        $this->sendEmail($subject, $content);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendCommandExecutionReport(array $data, Command $command): void
    {
        if (!$command->getStatusSendEmail()) {
            return;
        }

        $hasException = isset($data['exception']);

        if ($hasException) {
            $subject = 'ERREUR CRITIQUE - Exécution de commande';
        } elseif (isset($data['statistiques'])) {
            $hasErrors = !empty($data['statistiques']['resultats']['details']);

            if ($hasErrors) {
                $subject = 'Erreurs - Exécution de commande';
            } else {
                $subject = 'SUCCESS - Exécution de commande';
            }
        } else {
            $subject = 'ERREUR - Exécution de commande';
        }

        try {
            $html = $this->templateService->renderTemplate('sync_articles_report', [
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            $html = $this->twig->render('emails/sync_articles_report.html.twig', [
                'data' => $data,
            ]);
        }

        $this->sendEmail($subject, $html, null, null, null, true);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendActivitiesSyncReport(array $data, Command $command): void
    {
        if (!$command->getStatusSendEmail()) {
            return;
        }

        $hasException = isset($data['exception']);
        $hasErrors = ($data['statistiques']['activites']['errors'] ?? 0) > 0 || 
                    ($data['statistiques']['creneaux']['errors'] ?? 0) > 0;

        if (!$hasException && !$hasErrors) {
            return;
        }

        if ($hasException) {
            $subject = 'ERREUR CRITIQUE - Synchronisation des activités et créneaux';
        } else {
            $subject = 'Erreurs - Synchronisation des activités et créneaux';
        }

        $html = $this->twig->render('emails/sync_activities_report.html.twig', [
            'data' => $data,
        ]);

        $this->sendEmail($subject, $html, null, null, null, true);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendCurrencySyncReport(array $data, Command $command): void
    {
        if (!$command->getStatusSendEmail()) {
            return;
        }

        $hasException = isset($data['exception']);
        $hasErrors = ($data['statistiques']['errors'] ?? 0) > 0;

        if ($hasException) {
            $subject = 'ERREUR CRITIQUE - Synchronisation des devises';
        } elseif ($hasErrors) {
            $subject = 'Erreurs - Synchronisation des devises';
        } else {
            $subject = 'SUCCESS - Synchronisation des devises';
        }

        $html = $this->twig->render('emails/sync_currencies_report.html.twig', [
            'data' => $data,
        ]);

        $this->sendEmail($subject, $html, null, null, null, true);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendDelockCouponReport(array $data, Command $command): void
    {
        if (!$command->getStatusSendEmail()) {
            return;
        }

        $html = $this->twig->render('emails/delock_coupon_report.html.twig', [
            'resume' => $data,
        ]);

        $this->sendEmail('SUCCESS - Exécution de commande', $html, null, null, null, true);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendInterventionCleanupReport(array $data, Command $command): void
    {
        if (!$command->getStatusSendEmail()) {
            return;
        }

        $hasException = isset($data['exception']);
        $hasErrors = ($data['error_count'] ?? 0) > 0;

        if ($hasException) {
            $subject = 'ERREUR CRITIQUE - Nettoyage des interventions';
        } elseif ($hasErrors) {
            $subject = 'Erreurs - Nettoyage des interventions';
        } else {
            $subject = 'SUCCESS - Nettoyage des interventions';
        }

        $html = $this->twig->render('emails/intervention_cleanup_report.html.twig', [
            'data' => $data,
        ]);

        $this->sendEmail($subject, $html, null, null, null, true);
    }
}
