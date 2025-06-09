<?php

namespace CandlewaxGames\Services;

/**
 * Contains the functionality required to send emails.
 */
class Mail
{
    /**
     * Attempts to email the $recipient, from the address defined by $sender.
     *
     * @param string $recipient The address of the recipient.
     * @param string $subject The subject of the email.
     * @param string $message The message body of the email.
     * @param string $sender The email address of the sender.
     * @return bool Whether the email was successfully accepted for delivery.
     */
    public function send(string $recipient, string $subject, string $message, string $sender): bool
    {
        // To send HTML mail, the Content-type header must be set.
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

        // Additional headers.
        $headers .= 'Return-Path: ' . $sender . " \r\n";
        $headers .= 'To: James Fraser <' . $recipient . '>' . "\r\n";
        $headers .= 'From: Candlewax Games Website <' . $sender . ">\r\n";

        // Return true if the email was accepted for delivery.
        return mail($recipient, $subject, $message, $headers, "-f" . $sender);
    }
}
