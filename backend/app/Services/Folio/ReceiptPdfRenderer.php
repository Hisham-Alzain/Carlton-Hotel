<?php

namespace App\Services\Folio;

use App\Exceptions\ExternalServiceException;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Renders a receipt to PDF.
 *
 * mpdf rather than dompdf because Arabic is a first-class locale here: dompdf
 * does not shape Arabic glyphs or handle RTL, so AR receipts would come out as
 * disconnected, reversed letters. Rendered on demand rather than stored — a
 * one-page receipt renders in well under a second, and pre-generating would
 * create an invalidation problem for folios that are not settled yet.
 */
class ReceiptPdfRenderer
{
    public function render(array $receipt, string $locale): string
    {
        $isRtl = $locale === 'ar';

        $html = view('pdf.receipt', [
            'receipt' => $receipt,
            'locale'  => $locale,
            'isRtl'   => $isRtl,
        ])->render();

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $mpdf = new Mpdf([
                'mode'            => 'utf-8',
                'format'          => 'A4',
                'directionality'  => $isRtl ? 'rtl' : 'ltr',
                'autoScriptToLang' => true,
                'autoLangToFont'   => true,
                'tempDir'         => $tempDir,
            ]);

            $mpdf->WriteHTML($html);

            return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        } catch (MpdfException $e) {
            throw new ExternalServiceException(__('custom.errors.external_service_error'), [], 0, $e);
        }
    }
}
