<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use RuntimeException;
use Throwable;

final class CertificateArtifactService
{
    private const TOKEN_BYTES = 32;

    private const NUMBER_BYTES = 16;

    private const FILENAME_BYTES = 16;

    private string $storedPath;

    public function __construct(
        private readonly string $root,
        string $storedPath = 'certificates',
        private readonly string $verificationBaseUrl = '/certificates/verify',
    ) {
        $this->storedPath = trim($storedPath, '/');
        if ($this->storedPath === '' || str_contains($this->storedPath, "\0")) {
            throw new RuntimeException('The certificate storage path is invalid.');
        }
    }

    /** @return array{certificate_number:string,raw_token:string,verification_token_hash:string,pdf_path:string} */
    public function generate(array $displayData): array
    {
        $root = $this->resolvedRoot();
        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTES));
        $certificateNumber = 'OEMS-CERT-' . strtoupper(bin2hex(random_bytes(self::NUMBER_BYTES)));
        $filename = 'certificate-' . bin2hex(random_bytes(self::FILENAME_BYTES)) . '.pdf';
        $path = $this->storedPath . '/' . $filename;
        $target = $root . DIRECTORY_SEPARATOR . $filename;

        try {
            $this->writePdf(
                $target,
                $certificateNumber,
                rtrim(trim($this->verificationBaseUrl), '/') . '/' . rawurlencode($rawToken),
                $displayData,
            );
        } catch (Throwable $exception) {
            $this->delete($path);
            throw $exception;
        }

        return [
            'certificate_number' => $certificateNumber,
            'raw_token' => $rawToken,
            'verification_token_hash' => hash('sha256', $rawToken),
            'pdf_path' => $path,
        ];
    }

    public function resolvePath(string $path): ?string
    {
        $filename = $this->filename($path);
        $root = $this->existingRoot();
        if ($filename === null || $root === null) {
            return null;
        }
        $candidate = $root . DIRECTORY_SEPARATOR . $filename;
        if (is_link($candidate)) {
            return null;
        }
        $resolved = realpath($candidate);

        return $resolved !== false
            && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
            && is_file($resolved)
            ? $resolved
            : null;
    }

    public function delete(?string $path): bool
    {
        if ($path === null) {
            return true;
        }
        $filename = $this->filename($path);
        $root = $this->existingRoot();
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
        if ($resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || !is_file($resolved)) {
            return false;
        }

        return @unlink($resolved);
    }

    private function writePdf(string $target, string $number, string $verificationUrl, array $displayData): void
    {
        if ($verificationUrl === '/' || strlen($verificationUrl) > 512 || filter_var($verificationUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('The certificate verification URL is invalid.');
        }
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->SetCompression(false);
        $pdf->SetTitle('OEMS Attendance Certificate');
        $pdf->SetCreator('OEMS');
        $pdf->AddPage();
        $pdf->SetDrawColor(61, 86, 214);
        $pdf->SetLineWidth(1.2);
        $pdf->Rect(12, 12, 273, 186);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->SetFont('Arial', 'B', 25);
        $pdf->Ln(22);
        $pdf->Cell(0, 14, 'OEMS Attendance Certificate', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 13);
        $pdf->Ln(8);
        $pdf->Cell(0, 8, 'This certificate recognizes', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->Cell(0, 13, $this->plain($displayData['participant_name'] ?? null, 120), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, 'for verified attendance at', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->MultiCell(0, 11, $this->plain($displayData['event_title'] ?? null, 160), 0, 'C');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Ln(5);
        $pdf->Cell(0, 7, 'Completed: ' . $this->plain($displayData['completion_date'] ?? null, 80), 0, 1, 'C');
        $pdf->Cell(0, 7, 'Issued: ' . $this->plain($displayData['issued_at'] ?? null, 80), 0, 1, 'C');
        $pdf->Cell(0, 7, 'Certificate: ' . $number, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Ln(5);
        $pdf->MultiCell(0, 5, 'Verify authenticity: ' . $verificationUrl, 0, 'C');
        $pdf->Output('F', $target);
        if (!is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('The certificate PDF could not be generated.');
        }
    }

    private function plain(mixed $value, int $limit): string
    {
        $plain = is_scalar($value) || $value === null ? (string) $value : '';
        $plain = strip_tags(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $plain) ?? '';
        $plain = preg_replace('/\s+/u', ' ', trim($plain)) ?? '';
        $plain = mb_substr($plain, 0, $limit, 'UTF-8');

        return mb_convert_encoding($plain, 'ISO-8859-1', 'UTF-8');
    }

    private function filename(string $path): ?string
    {
        $prefix = $this->storedPath . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }
        $filename = substr($path, strlen($prefix));

        return $filename !== '' && basename($filename) === $filename && !str_contains($filename, "\0")
            ? $filename
            : null;
    }

    private function resolvedRoot(): string
    {
        if (is_link($this->root)) {
            throw new RuntimeException('The certificate directory may not be a symlink.');
        }
        if (!is_dir($this->root) && !mkdir($this->root, 0750, true) && !is_dir($this->root)) {
            throw new RuntimeException('The certificate directory could not be created.');
        }
        @chmod($this->root, 0750);
        $resolved = realpath($this->root);
        if ($resolved === false || !is_writable($resolved)) {
            throw new RuntimeException('The certificate directory is unavailable.');
        }

        return $resolved;
    }

    private function existingRoot(): ?string
    {
        if (is_link($this->root)) {
            return null;
        }
        $resolved = realpath($this->root);

        return $resolved !== false && is_dir($resolved) ? $resolved : null;
    }
}
