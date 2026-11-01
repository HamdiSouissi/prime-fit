<?php
declare(strict_types=1);
require_once __DIR__.'/StockHistory.php';
/** Écriture CSV UTF-8 compatible tableurs, protection des champs texte. */
class StockHistoryCsv {
    public static function safe($value): string {
        $value=(string)$value;
        return preg_match('/^[\\t\\r\\n]/u',$value) || preg_match('/^[\\s\\p{Z}\\x00-\\x1F\\x7F\\x{FEFF}]*[=+@-]/u',$value) ? "'".$value : $value;
    }
    /** $out est un flux ouvert en écriture ; le lecteur en conserve la propriété. */
    public static function write($out,StockHistory $history,array $product,array $filters): void {
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['ID mouvement','ID produit','Produit actuel','Référence actuelle','Date','Type','Écart','Motif','Auteur actuel','ID vente'],';','"','');
        $history->export((int)$product['id'],$filters,static function(array $row) use ($out,$product): void {
            fputcsv($out,[(string)$row['id'],(string)$product['id'],self::safe($product['name']),self::safe($product['sku']),date('d/m/Y H:i:s',strtotime($row['created_at'])),self::safe(StockHistory::REASONS[$row['reason']] ?? $row['reason']),(string)(int)$row['quantity_change'],self::safe($row['note'] ?? ''),self::safe($row['author'] ?? ''),$row['sale_id']===null ? '' : (string)(int)$row['sale_id']],';','"','');
        });
    }
}
