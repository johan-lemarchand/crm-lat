<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

readonly class CsvExportService
{
    public function __construct(
        private SerializerInterface $serializer
    ) {}

    public function exportData(array $data, string $format = 'csv'): Response
    {
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM pour Excel si nécessaire
        if ($format === 'excel') {
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        }

        // En-têtes
        if (!empty($data)) {
            $firstRow = reset($data);
            $headers = array_keys($firstRow);
            fputcsv($handle, $headers, ';');
        }

        // Données
        foreach ($data as $row) {
            $formattedRow = [];
            foreach ($row as $key => $value) {
                if (is_array($value)) {
                    // Formater les tableaux en JSON lisible
                    $formattedRow[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    $formattedRow[$key] = $value;
                }
            }
            fputcsv($handle, $formattedRow, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($content);
        $response->headers->set('Content-Type', $format === 'json' ? 'application/json' : 'text/csv; charset=UTF-8');
        
        $filename = sprintf(
            'export_logs_%s_%s.%s',
            date('Y-m-d_His'),
            $format === 'json' ? 'json' : 'csv',
            $format === 'json' ? 'json' : 'csv'
        );
        
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    private function toCsv(array $data, array $headers = []): string
    {
        $handle = fopen('php://temp', 'r+');

        // En-têtes
        if (empty($headers) && !empty($data)) {
            $headers = is_array($data[0]) ? array_keys($data[0]) : array_keys($data);
        }
        fputcsv($handle, $headers, ';');

        // Données
        foreach ($data as $row) {
            if (!is_array($row)) {
                $row = ['value' => $row];
            }
            $rowData = array_map(function ($value) {
                if (is_array($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                return $value;
            }, $row);
            fputcsv($handle, $rowData, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function toExcel(array $data, array $headers = []): string
    {
        // UTF-8 BOM pour Excel
        return chr(0xEF).chr(0xBB).chr(0xBF).$this->toCsv($data, $headers);
    }

    private function toJson(array $data): string
    {
        return $this->serializer->serialize($data, 'json', [
            'json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ]);
    }
}
