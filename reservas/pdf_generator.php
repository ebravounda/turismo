<?php
/**
 * Minimal pure-PHP PDF generator for TukTuk Norris reservation confirmations.
 * No external dependencies — generates valid PDF 1.4 binary.
 */
class ReservaPDF {
    private array $objects = [];
    private array $offsets = [];
    private int   $n       = 0;

    private function newobj(string $data): int {
        $this->n++;
        $this->objects[$this->n] = $data;
        return $this->n;
    }

    private function stream(string $content): string {
        return "<<\n/Length " . strlen($content) . "\n>>\nstream\n" . $content . "\nendstream";
    }

    /** Escape a string for PDF text operations */
    private function esc(string $s): string {
        $s = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        return str_replace(['\\','(',')',"\r"], ['\\\\','\\(','\\)','\\r'], $s);
    }

    /** Build a text line: x y Td (text) Tj */
    private function line(float $x, float $y, string $text, int $size = 11, bool $bold = false): string {
        $font = $bold ? 'F2' : 'F1';
        return "BT\n/{$font} {$size} Tf\n{$x} {$y} Td\n({$this->esc($text)}) Tj\nET\n";
    }

    public function generate(array $r): string {
        // Page dimensions: A4 portrait (595 x 842 pt)
        $W = 595; $H = 842;
        $margin = 50;

        // ── Content stream ───────────────────────────────────────
        $c = '';

        // Orange header bar
        $c .= "0.941 0.647 0.000 rg\n"; // #f0a500
        $c .= "{$margin} " . ($H - 90) . " " . ($W - 2*$margin) . " 60 re f\n";

        // Header text (white)
        $c .= "1 1 1 rg\n";
        $c .= "BT\n/F2 22 Tf\n" . ($margin + 10) . " " . ($H - 55) . " Td\n(TukTuk Norris) Tj\nET\n";
        $c .= "BT\n/F1 11 Tf\n" . ($margin + 10) . " " . ($H - 72) . " Td\n(Confirmacion de Reserva) Tj\nET\n";

        // Right side of header: reservation ID
        $idLabel = 'Ref: ' . $r['id'];
        $c .= "BT\n/F2 11 Tf\n" . ($W - $margin - 110) . " " . ($H - 55) . " Td\n({$this->esc($idLabel)}) Tj\nET\n";

        // Reset color to dark
        $c .= "0.1 0.1 0.1 rg\n";

        $y = $H - 130;

        // Section: Datos del cliente
        $c .= "0.941 0.647 0.000 rg\n";
        $c .= "BT\n/F2 12 Tf\n{$margin} {$y} Td\n(Datos del cliente) Tj\nET\n";
        $c .= "0.1 0.1 0.1 rg\n";
        $y -= 5;
        $c .= "0.880 0.880 0.880 rg\n{$margin} {$y} " . ($W - 2*$margin) . " 1 re f\n0.1 0.1 0.1 rg\n";
        $y -= 20;

        $fields_client = [
            ['Nombre:',    $r['nombre']   ?? ''],
            ['Email:',     $r['email']    ?? ''],
            ['Telefono:',  $r['telefono'] ?? ''],
        ];
        foreach ($fields_client as [$label, $val]) {
            $c .= "BT\n/F2 10 Tf\n{$margin} {$y} Td\n({$this->esc($label)}) Tj\nET\n";
            $c .= "BT\n/F1 10 Tf\n" . ($margin + 100) . " {$y} Td\n({$this->esc($val)}) Tj\nET\n";
            $y -= 18;
        }

        $y -= 10;

        // Section: Detalles del tour
        $c .= "0.941 0.647 0.000 rg\n";
        $c .= "BT\n/F2 12 Tf\n{$margin} {$y} Td\n(Detalles del tour) Tj\nET\n";
        $c .= "0.1 0.1 0.1 rg\n";
        $y -= 5;
        $c .= "0.880 0.880 0.880 rg\n{$margin} {$y} " . ($W - 2*$margin) . " 1 re f\n0.1 0.1 0.1 rg\n";
        $y -= 20;

        $fechaFmt = '';
        if (!empty($r['fecha'])) {
            $fechaFmt = date('d/m/Y', strtotime($r['fecha']));
        }
        $fields_tour = [
            ['Tour:',     $r['tipo_tour'] ?? ''],
            ['Fecha:',    $fechaFmt],
            ['Hora:',     isset($r['hora']) ? substr($r['hora'], 0, 5) . ' h' : ''],
            ['Personas:', (string)($r['personas'] ?? '')],
            ['Idioma:',   $r['idioma']    ?? 'Espanol'],
        ];
        foreach ($fields_tour as [$label, $val]) {
            $c .= "BT\n/F2 10 Tf\n{$margin} {$y} Td\n({$this->esc($label)}) Tj\nET\n";
            $c .= "BT\n/F1 10 Tf\n" . ($margin + 100) . " {$y} Td\n({$this->esc($val)}) Tj\nET\n";
            $y -= 18;
        }

        $y -= 10;

        // Section: Pago
        $c .= "0.941 0.647 0.000 rg\n";
        $c .= "BT\n/F2 12 Tf\n{$margin} {$y} Td\n(Informacion de pago) Tj\nET\n";
        $c .= "0.1 0.1 0.1 rg\n";
        $y -= 5;
        $c .= "0.880 0.880 0.880 rg\n{$margin} {$y} " . ($W - 2*$margin) . " 1 re f\n0.1 0.1 0.1 rg\n";
        $y -= 20;

        $pagadoStr = !empty($r['pagado']) ? 'Pagado online' : 'Pendiente de pago';
        $montoStr  = !empty($r['monto']) && $r['monto'] > 0
            ? number_format((float)$r['monto'], 2, ',', '.') . ' EUR'
            : 'A consultar';
        $stripeStr = !empty($r['stripe_session_id']) ? substr($r['stripe_session_id'], 0, 50) : '-';

        $fields_pay = [
            ['Estado:',    $pagadoStr],
            ['Importe:',   $montoStr],
            ['Ref. Stripe:',$stripeStr],
        ];
        foreach ($fields_pay as [$label, $val]) {
            $c .= "BT\n/F2 10 Tf\n{$margin} {$y} Td\n({$this->esc($label)}) Tj\nET\n";
            $c .= "BT\n/F1 10 Tf\n" . ($margin + 100) . " {$y} Td\n({$this->esc($val)}) Tj\nET\n";
            $y -= 18;
        }

        // Message if not paid
        if (empty($r['pagado'])) {
            $y -= 10;
            $c .= "0.600 0.400 0.000 rg\n";
            $c .= "BT\n/F1 9 Tf\n{$margin} {$y} Td\n(El pago se realizara el dia del tour en efectivo o tarjeta.) Tj\nET\n";
            $c .= "0.1 0.1 0.1 rg\n";
        }

        // Section: Política de cancelación
        $y -= 10;
        $c .= "0.941 0.647 0.000 rg\n";
        $c .= "BT\n/F2 12 Tf\n{$margin} {$y} Td\n(Politica de cancelacion) Tj\nET\n";
        $c .= "0.1 0.1 0.1 rg\n";
        $y -= 5;
        $c .= "0.880 0.880 0.880 rg\n{$margin} {$y} " . ($W - 2*$margin) . " 1 re f\n0.1 0.1 0.1 rg\n";
        $y -= 20;
        $c .= "BT\n/F1 9 Tf\n{$margin} {$y} Td\n(Cancelacion gratuita hasta 24 horas antes del tour.) Tj\nET\n";
        $y -= 14;
        $c .= "BT\n/F1 9 Tf\n{$margin} {$y} Td\n(Cancelaciones con menos de 24 horas de antelacion: se devuelve el 50% del importe pagado.) Tj\nET\n";
        $y -= 14;
        $c .= "BT\n/F1 9 Tf\n{$margin} {$y} Td\n(Para gestionar una cancelacion contacta con nosotros en info@tuktuknorris.es o al +34 951 234 567.) Tj\nET\n";

        // Footer
        $c .= "0.600 0.600 0.600 rg\n";
        $footerY = 50;
        $c .= "0.880 0.880 0.880 rg\n{$margin} " . ($footerY + 15) . " " . ($W - 2*$margin) . " 1 re f\n";
        $c .= "0.600 0.600 0.600 rg\n";
        $c .= "BT\n/F1 9 Tf\n{$margin} {$footerY} Td\n(TukTuk Norris — tuktuknorris.es — Malaga, Espana) Tj\nET\n";
        $dateStr = date('d/m/Y H:i');
        $c .= "BT\n/F1 9 Tf\n" . ($W - $margin - 110) . " {$footerY} Td\n(Generado: {$dateStr}) Tj\nET\n";

        // ── Build PDF objects ────────────────────────────────────
        $catalog_id  = 1;
        $pages_id    = 2;
        $page_id     = 3;
        $content_id  = 4;
        $font1_id    = 5;
        $font2_id    = 6;

        $this->objects[1] = "<</Type /Catalog /Pages 2 0 R>>";
        $this->objects[2] = "<</Type /Pages /Kids [3 0 R] /Count 1>>";
        $this->objects[3] = "<</Type /Page /Parent 2 0 R /MediaBox [0 0 {$W} {$H}] "
                          . "/Contents 4 0 R "
                          . "/Resources <</Font <</F1 5 0 R /F2 6 0 R>>>>>>";
        $this->objects[4] = $this->stream($c);
        $this->objects[5] = "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>";
        $this->objects[6] = "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding>>";
        $this->n = 6;

        // ── Assemble PDF ─────────────────────────────────────────
        $out = "%PDF-1.4\n";
        for ($i = 1; $i <= $this->n; $i++) {
            $this->offsets[$i] = strlen($out);
            $out .= "{$i} 0 obj\n{$this->objects[$i]}\nendobj\n";
        }

        $xref_offset = strlen($out);
        $out .= "xref\n0 " . ($this->n + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $this->n; $i++) {
            $out .= str_pad($this->offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $out .= "trailer\n<</Size " . ($this->n + 1) . " /Root 1 0 R>>\n";
        $out .= "startxref\n{$xref_offset}\n%%EOF\n";

        return $out;
    }
}
