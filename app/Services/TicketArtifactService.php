<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;
use Throwable;

final class TicketArtifactService
{
    private const TOKEN_BYTES = 32;

    private const FILENAME_BYTES = 16;

    private const EVENT_TITLE_LIMIT = 160;

    private const DISPLAY_VALUE_LIMIT = 120;

    private string $publicPath;

    public function __construct(
        private readonly string $uploadRoot,
        string $publicPath = 'uploads/tickets',
        private readonly string $checkInUrl = '/organizer/check-in',
    ) {
        $this->publicPath = trim($publicPath, '/');

        if ($this->publicPath === '' || str_contains($this->publicPath, "\0")) {
            throw new RuntimeException('The ticket public path is invalid.');
        }
    }

    /**
     * @param array<string, mixed> $displayData
     * @return array{ticket_number: string, raw_token: string, qr_payload_hash: string, qr_path: string, pdf_path: string}
     */
    public function generate(array $displayData): array
    {
        $root = $this->resolvedUploadRoot();
        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTES));
        $ticketNumber = 'OEMS-' . strtoupper(bin2hex(random_bytes(self::FILENAME_BYTES)));
        $qrFilename = $this->uniqueFilename($root, 'qr-', 'png');
        $pdfFilename = $this->uniqueFilename($root, 'ticket-', 'pdf');
        $qrPath = $this->relativePath($qrFilename);
        $pdfPath = $this->relativePath($pdfFilename);
        $qrFile = $root . DIRECTORY_SEPARATOR . $qrFilename;
        $pdfFile = $root . DIRECTORY_SEPARATOR . $pdfFilename;

        try {
            $this->writeQrCode($this->checkInPayload($rawToken), $qrFile);
            $this->writePdf($pdfFile, $qrFile, $ticketNumber, $displayData);
        } catch (Throwable $exception) {
            $this->delete($qrPath);
            $this->delete($pdfPath);

            throw $exception;
        }

        return [
            'ticket_number' => $ticketNumber,
            'raw_token' => $rawToken,
            'qr_payload_hash' => hash('sha256', $rawToken),
            'qr_path' => $qrPath,
            'pdf_path' => $pdfPath,
        ];
    }

    public function resolvePublicPath(string $path): ?string
    {
        $filename = $this->filenameFromPublicPath($path);

        if ($filename === null) {
            return null;
        }

        $root = $this->existingUploadRoot();

        if ($root === null) {
            return null;
        }

        $target = realpath($root . DIRECTORY_SEPARATOR . $filename);

        if ($target === false
            || !str_starts_with($target, $root . DIRECTORY_SEPARATOR)
            || !is_file($target)) {
            return null;
        }

        return $target;
    }

    public function delete(?string $path): bool
    {
        if ($path === null) {
            return true;
        }

        $filename = $this->filenameFromPublicPath($path);
        $root = $this->existingUploadRoot();

        if ($filename === null || $root === null) {
            return false;
        }

        $candidate = $root . DIRECTORY_SEPARATOR . $filename;
        if (is_link($candidate)) {
            return false;
        }

        if (!file_exists($candidate)) {
            return true;
        }

        $resolved = realpath($candidate);

        if ($resolved === false
            || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
            || !is_file($resolved)) {
            return false;
        }

        return @unlink($resolved);
    }

    private function writeQrCode(string $payload, string $destination): void
    {
        $qrCode = QrCode::create($payload)
            ->setSize(240)
            ->setMargin(10);

        (new PngWriter())->write($qrCode)->saveToFile($destination);

        if (!is_file($destination)) {
            throw new RuntimeException('The ticket QR code could not be generated.');
        }
    }

    /**
     * @param array<string, mixed> $displayData
     */
    private function writePdf(
        string $destination,
        string $qrFile,
        string $ticketNumber,
        array $displayData,
    ): void {
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetCompression(false);
        $pdf->SetTitle('OEMS Ticket');
        $pdf->SetCreator('OEMS');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->Cell(0, 12, 'OEMS Ticket', 0, 1);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, 'Ticket: ' . $ticketNumber, 0, 1);
        $pdf->Ln(4);
        $pdf->MultiCell(115, 7, 'Event: ' . $this->displayValue($displayData['event_title'] ?? null, self::EVENT_TITLE_LIMIT));
        $pdf->MultiCell(115, 7, 'Date: ' . $this->displayValue($displayData['event_starts_at'] ?? null, self::DISPLAY_VALUE_LIMIT));
        $pdf->MultiCell(115, 7, 'Venue: ' . $this->displayValue($displayData['venue_name'] ?? null, self::DISPLAY_VALUE_LIMIT));
        $pdf->MultiCell(115, 7, 'Participant: ' . $this->displayValue($displayData['participant_name'] ?? null, self::DISPLAY_VALUE_LIMIT));
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(20, 115);
        $pdf->MultiCell(115, 5, 'Present this ticket at check-in. The QR code contains a one-time opaque token.');
        $pdf->Image($qrFile, 145, 40, 45, 45, 'PNG');
        $pdf->Output('F', $destination);

        if (!is_file($destination)) {
            throw new RuntimeException('The ticket PDF could not be generated.');
        }
    }

    private function checkInPayload(string $rawToken): string
    {
        $baseUrl = trim($this->checkInUrl);

        if ($baseUrl === '' || str_contains($baseUrl, "\0")) {
            throw new RuntimeException('The ticket check-in URL is invalid.');
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'token=' . rawurlencode($rawToken);
    }

    private function displayValue(mixed $value, int $limit): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $plainText = strip_tags(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainText = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $plainText) ?? '';
        $plainText = preg_replace('/\s+/u', ' ', trim($plainText)) ?? '';
        $plainText = mb_substr($plainText, 0, $limit, 'UTF-8');

        return mb_convert_encoding($plainText, 'ISO-8859-1', 'UTF-8');
    }

    private function uniqueFilename(string $root, string $prefix, string $extension): string
    {
        do {
            $filename = $prefix . bin2hex(random_bytes(self::FILENAME_BYTES)) . '.' . $extension;
            $path = $root . DIRECTORY_SEPARATOR . $filename;
        } while (file_exists($path) || is_link($path));

        return $filename;
    }

    private function relativePath(string $filename): string
    {
        return $this->publicPath . '/' . $filename;
    }

    private function filenameFromPublicPath(string $path): ?string
    {
        $prefix = $this->publicPath . '/';

        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $filename = substr($path, strlen($prefix));

        if ($filename === ''
            || basename($filename) !== $filename
            || str_contains($filename, "\0")) {
            return null;
        }

        return $filename;
    }

    private function resolvedUploadRoot(): string
    {
        if (is_link($this->uploadRoot)) {
            throw new RuntimeException('The ticket upload root may not be a symlink.');
        }

        if (!is_dir($this->uploadRoot) && !@mkdir($this->uploadRoot, 0775, true)) {
            throw new RuntimeException('The ticket upload directory could not be created.');
        }

        $root = $this->existingUploadRoot();

        if ($root === null || !is_writable($root)) {
            throw new RuntimeException('The ticket upload directory is unavailable.');
        }

        return $root;
    }

    private function existingUploadRoot(): ?string
    {
        if (is_link($this->uploadRoot)) {
            return null;
        }

        $root = realpath($this->uploadRoot);

        return $root !== false && is_dir($root) ? $root : null;
    }
}
