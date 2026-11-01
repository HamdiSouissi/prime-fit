<?php
declare(strict_types=1);
/** Initiales de la première et de la dernière partie du nom, sans hypothèse sur sa structure. */
final class AccountInitials {
    public static function fromName(string $name): string {
        $parts=preg_split('/\s+/u',trim($name),-1,PREG_SPLIT_NO_EMPTY);
        if (!$parts) return '?';
        $first=mb_substr($parts[0],0,1,'UTF-8');
        $last=count($parts)>1 ? mb_substr($parts[count($parts)-1],0,1,'UTF-8') : '';
        return mb_strtoupper($first.$last,'UTF-8');
    }
}
