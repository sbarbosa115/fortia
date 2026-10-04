<?php

namespace App\Identity\UI\Http\Input;

use App\Identity\Domain\Model\SmtpConfiguration;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The smtp_* fields of the System tab, shape only (the domain checks the server as a whole). An empty smtp_host
 * removes the server; an empty smtp_password removes the password; a field not sent keeps its saved value.
 */
trait SmtpServerFields
{
    #[Assert\Length(max: 255)]
    public ?string $smtp_host = null;

    #[Assert\Range(min: 1, max: 65535)]
    public ?int $smtp_port = null;

    #[Assert\Choice(choices: SmtpConfiguration::ENCRYPTIONS)]
    public ?string $smtp_encryption = null;

    #[Assert\Length(max: 255)]
    public ?string $smtp_username = null;

    #[Assert\Length(max: 1024)]
    public ?string $smtp_password = null;

    #[Assert\Length(max: 254)]
    public ?string $smtp_from_email = null;

    #[Assert\Length(max: 100)]
    public ?string $smtp_from_name = null;

    /** @return array<string, mixed> the fields sent, by name */
    public function provided(): array
    {
        $values = [];
        foreach ($this->providedFields() as $field) {
            $values[$field] = $this->{$field};
        }

        return $values;
    }
}
