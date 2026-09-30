<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Request;

use App\Shared\Domain\Error\Rejected;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Resolves #[Payload] arguments: JSON → Input DTO → validation (see Payload). */
final class PayloadValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly DenormalizerInterface $denormalizer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @return iterable<object> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(Payload::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
        $class = $argument->getType();
        if (!$attribute instanceof Payload || null === $class || !class_exists($class)) {
            return [];
        }

        $data = self::decode($request);

        if (!$attribute->allowExtraFields) {
            $known = array_map(static fn (\ReflectionProperty $p): string => $p->getName(), (new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC));
            $extra = array_values(array_diff(array_map('strval', array_keys($data)), $known));
            if ([] !== $extra) {
                throw new ValidationFailed(array_map(static fn (string $f): array => ['field' => $f, 'message' => 'This field was not expected.'], $extra));
            }
        }

        try {
            /** @var object $input */
            $input = $this->denormalizer->denormalize($data, $class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,
                AbstractObjectNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            ]);
        } catch (PartialDenormalizationException $e) {
            throw new ValidationFailed(array_map(
                static fn (NotNormalizableValueException $error): array => [
                    'field' => (string) $error->getPath(),
                    'message' => \sprintf('This value should be of type %s.', implode('|', $error->getExpectedTypes() ?? ['?'])),
                ],
                $e->getErrors(),
            ));
        } catch (NotNormalizableValueException|ExtraAttributesException $e) {
            throw ValidationFailed::field('', $e->getMessage());
        }

        if ($input instanceof TracksProvidedFields) {
            $input->markProvided(array_values(array_map('strval', array_keys($data))));
        }

        $violations = $this->validator->validate($input, null, $attribute->groups);
        if (\count($violations) > 0) {
            $list = [];
            foreach ($violations as $violation) {
                $list[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
            }
            throw new ValidationFailed($list);
        }

        return [$input];
    }

    /**
     * The JSON object of the body; an empty body is {}.
     *
     * @return array<string, mixed>
     */
    public static function decode(Request $request): array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [];
        }
        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Rejected('INVALID_JSON', 'The body is not valid JSON.');
        }
        if (!\is_array($data) || (array_is_list($data) && [] !== $data)) {
            throw ValidationFailed::field('', 'The body must be a JSON object.');
        }

        return $data;
    }
}
