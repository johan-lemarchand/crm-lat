<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailTemplateService
{
    public function __construct(
        private readonly EmailTemplateRepository $templateRepository
    ) {
    }

    /**
     * @return EmailTemplate[]
     */
    public function getAllTemplates(): array
    {
        return $this->templateRepository->findAllSorted();
    }

    public function getTemplate(string $name): EmailTemplate
    {
        $template = $this->templateRepository->findOneBy(['name' => $name]);
        
        if (!$template) {
            throw new NotFoundHttpException(sprintf('Template "%s" non trouvé', $name));
        }
        
        return $template;
    }

    public function saveTemplate(string $name, string $content): EmailTemplate
    {
        $template = $this->templateRepository->findOneBy(['name' => $name]);
        
        if (!$template) {
            $template = new EmailTemplate();
            $template->setName($name);
        }
        
        $template->setContent($content);
        $this->templateRepository->save($template, true);
        
        return $template;
    }

    public function deleteTemplate(string $name): void
    {
        $template = $this->templateRepository->findOneBy(['name' => $name]);
        
        if ($template) {
            $this->templateRepository->remove($template, true);
        }
    }

    public function renderTemplate(string $templateName, array $data): string
    {
        $template = $this->getTemplate($templateName);
        $content = $template->getContent();
        
        // Remplacer les placeholders simples
        foreach ($data as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $content = str_replace("{{{$key}}}", (string)$value, $content);
            }
        }
        
        // Traitement des structures complexes
        $content = $this->processComplexData($content, $data);
        
        return $content;
    }

    private function processComplexData(string $content, array $data): string
    {
        // Traitement des boucles FOREACH
        preg_match_all('/<!-- FOREACH ([a-z0-9_.]+) -->(.*?)<!-- ENDFOREACH -->/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $path = explode('.', $match[1]);
            $loopData = $this->getNestedValue($data, $path);
            $template = $match[2];
            
            $replacement = '';
            if (is_array($loopData)) {
                foreach ($loopData as $item) {
                    $itemContent = $template;
                    foreach ($item as $key => $value) {
                        if (is_scalar($value)) {
                            $itemContent = str_replace("{{item.$key}}", (string)$value, $itemContent);
                        }
                    }
                    $replacement .= $itemContent;
                }
            }
            
            $content = str_replace($match[0], $replacement, $content);
        }
        
        // Traitement des conditions IF
        preg_match_all('/<!-- IF ([a-z0-9_.]+) -->(.*?)<!-- ENDIF -->/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $path = explode('.', $match[1]);
            $conditionValue = $this->getNestedValue($data, $path);
            
            if ($conditionValue) {
                $content = str_replace($match[0], $match[2], $content);
            } else {
                $content = str_replace($match[0], '', $content);
            }
        }
        
        return $content;
    }

    private function getNestedValue(array $data, array $path)
    {
        $current = $data;
        foreach ($path as $key) {
            if (!isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }
}
