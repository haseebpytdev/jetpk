<?php

namespace App\Services\Suppliers\OneApi\Transport;

use XMLWriter;

class OneApiSoapSecurityBuilder
{
    public function buildUsernameToken(string $username, string $password): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startElement('wsse:Security');
        $writer->writeAttribute('xmlns:wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $writer->startElement('wsse:UsernameToken');
        $writer->writeAttribute('wsu:Id', 'UsernameToken-'.bin2hex(random_bytes(8)));
        $writer->writeAttribute('xmlns:wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
        $writer->writeElement('wsse:Username', $username);
        $writer->startElement('wsse:Password');
        $writer->writeAttribute('Type', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText');
        $writer->text($password);
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        return $writer->outputMemory();
    }
}
