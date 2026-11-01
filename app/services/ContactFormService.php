<?php
declare(strict_types=1);

/** Validation et envoi d'un message public ; le transport ne garantit pas la livraison finale. */
final class ContactFormService {
    private $transport;
    public function __construct(private array $mail, ?callable $transport=null) {
        $this->transport=$transport ?? static fn(string $to,string $subject,string $body,string $headers): bool => @mail($to,$subject,$body,$headers);
    }
    public function configured(string $to): bool {
        $from=$this->mail['from'] ?? '';
        return !empty($this->mail['enabled']) && is_string($from) && self::validEmail($from) && self::validEmail($to);
    }
    private static function validEmail(string $email): bool {
        return strlen($email)<=254 && !preg_match('/[\r\n]/', $email) && filter_var($email,FILTER_VALIDATE_EMAIL)!==false;
    }
    /** @return array{0:array,1:string} */
    public function validate(array $input): array {
        $fields=[];
        foreach (['name','email','phone','message'] as $key) $fields[$key]=is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $name=$fields['name']; $email=$fields['email']; $phone=$fields['phone']; $message=$fields['message'];
        if ($name==='' || mb_strlen($name,'UTF-8')>100 || preg_match('/[\x00-\x1F\x7F]/',$name)) return [$fields,'Indiquez un nom valide (100 caractères maximum).'];
        if (!self::validEmail($email)) return [$fields,'Indiquez une adresse e-mail valide.'];
        if ($phone!=='' && (strlen($phone)>30 || !preg_match('/^\+?[0-9 ()-]{6,30}$/D',$phone))) return [$fields,'Indiquez un numéro de téléphone valide.'];
        if (mb_strlen($message,'UTF-8')<10 || mb_strlen($message,'UTF-8')>4000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message)) return [$fields,'Votre message doit contenir entre 10 et 4 000 caractères.'];
        return [$fields,''];
    }
    public function send(string $to,array $fields): bool {
        if (!$this->configured($to)) return false;
        $body="Nom : {$fields['name']}\r\nE-mail : {$fields['email']}\r\nTéléphone : ".($fields['phone'] ?: 'Non renseigné')."\r\n\r\nMessage :\r\n".str_replace(["\r\n","\r"],"\n",$fields['message']);
        $headers="From: {$this->mail['from']}\r\nReply-To: {$fields['email']}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
        try { return (bool)($this->transport)($to,'Nouveau message du site',chunk_split(base64_encode($body)), $headers); }
        catch (Throwable $ex) { error_log('Échec du transport du formulaire de contact.'); return false; }
    }
}
