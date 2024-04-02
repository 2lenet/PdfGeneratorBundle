<?php

namespace Lle\PdfGeneratorBundle\Generator;

use Lle\PdfGeneratorBundle\Lib\PdfIterable;
use Lle\PdfGeneratorBundle\Exception\ModelNotFoundException;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

class WordToPdfGenerator extends AbstractPdfGenerator
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccess,
        private Environment $twig,
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    private function compile(iterable $params, TemplateProcessor $templateProcessor, array $options): void
    {
        $duplicate = [];

        foreach ($templateProcessor->getVariables() as $variable) {
            try {
                [$exp, $root, $var, $img] = $this->getPathVar($variable);

                if (isset($params[$exp[0]]) && $params[$exp[0]] instanceof PdfIterable) {
                    $iterator = $params[$exp[0]];

                    if (!isset($duplicate[$exp[0]])) {
                        $templateProcessor->cloneRow($variable, count($iterator));
                        $duplicate[$exp[0]] = true;
                    }

                    $i = 0;
                    foreach ($iterator as $item) {
                        if ($this->isImgPath($variable, $matches)) {
                            $iteratedVariable = '@img[' . $matches[1] . ']#' . ++$i . ($matches[2] ?? '');
                        } else {
                            $iteratedVariable = $variable . '#' . ++$i;
                        }

                        $this->setVar($templateProcessor, $iteratedVariable, $item, $var, $img);
                    }
                } else {
                    $this->setVar($templateProcessor, $variable, $params, ($var) ? $root . '.' . $var : $root, $img);
                }
            } catch (\Exception $e) {
                if (isset($options[PdfGenerator::OPTION_EMPTY_NOTFOUND_VALUE]) && $options[PdfGenerator::OPTION_EMPTY_NOTFOUND_VALUE]) {
                    $templateProcessor->setValue($variable, $params[$variable] ?? '');
                } else {
                    $templateProcessor->setValue($variable, $params[$variable] ?? $variable);
                }
            }
        }
    }

    private function wordToPdf(string $source, iterable $params, string $savePath, array $options): void
    {
        $templateProcessor = new TemplateProcessor($source);

        $tmpFile = tempnam(sys_get_temp_dir(), 'tmp');
        $this->compile($params, $templateProcessor, $options);
        $templateProcessor->saveAs($tmpFile);
        /*if ($options['twig'] ?? false) {
            $process = new Process(['unoconvert ', '--convert-to', 'html', $tmpFile, $tmpFile . '.html']);
            $process->run();

            $template = $this->twig->createTemplate(\file_get_contents($tmpFile . '.html'));

            file_put_contents($tmpFile . '.html.twig', $template->render($params));
            $tmpFile = $tmpFile . '.html.twig';
        }*/
        $rep = $this->httpClient->request("POST", $this->parameterBag->get('lle.pdf.unoserver'), ['body' => file_get_contents($tmpFile)]);
        file_put_contents($savePath, $rep->getContent());
    }

    public function getVariables(string $source): array
    {
        $templateProcessor = new TemplateProcessor($source);

        $res = [];

        foreach ($templateProcessor->getVariables() as $variable) {
            if (array_key_exists($variable, $res)) {
                $res[$variable]++;
            } else {
                $res[$variable] = 1;
            }
        }

        return $res;
    }

    public function generate(string $source, iterable $params, string $savePath, array $options = []): void
    {
        if (!file_exists($source)) {
            if (!file_exists($source . '.docx')) {
                throw new ModelNotFoundException($source . '(.docx) not found');
            } else {
                $source = $source . '.docx';
            }
        }

        $this->wordToPdf($source, $params, $savePath, $options);
    }

    public static function getName(): string
    {
        return 'word_to_pdf';
    }

    private function getPathVar(string $variable): array
    {
        if ($this->isImgPath($variable, $matches)) {
            $variable = $matches[1];
        }

        $exp = explode('.', $variable, 2);
        $root = '[' . $exp[0] . ']';
        $var = $exp[1] ?? null;

        return [$exp, $root, $var, null];
    }

    private function getImg(object|array $root, string|PropertyPathInterface $var, ?array $match): array
    {
        $value = (string)$this->propertyAccess->getValue($root, $var);

        if (substr($value, 0, 1) === '/') {
            $img = ['path' => $value];
        } else {
            $img = ['path' => $this->pdfPath . $value];
        }

        if (isset($match[2])) {
            $img['width'] = $match[3];
            $img['height'] = $match[4];
        }

        return $img;
    }

    private function getValue(object|array $root, string|PropertyPathInterface $var): mixed
    {
        $value = null;

        if ($var) {
            $value = $this->propertyAccess->getValue($root, $var);
        }

        if ($value instanceof \DateTime) {
            $value = $value->format('d/m/Y');
        }

        return $value;
    }

    private function setVar(
        TemplateProcessor $templateProcessor,
        string $variable,
        array|object $root,
        string|PropertyPathInterface $var,
        ?array $match,
    ): void {
        if (mb_substr($variable, 0, 4, "UTF-8") === '@img') {
            $img = $this->getImg($root, $var, $match);

            $templateProcessor->setImageValue($variable, $img);
        } else {
            $value = $this->getValue($root, $var);

            if ($value instanceof AbstractElement) {
                $templateProcessor->setComplexBlock($variable, $value);
            } else {
                $value1 = str_replace("\n", '</w:t><w:br/><w:t>', $value);
                $templateProcessor->setValue($variable, htmlspecialchars($value1));
            }
        }
    }

    private function isImgPath(string $path, ?array &$matches): false|int
    {
        return preg_match('#^@img\[([A-Za-z0-9\.\[\]]+)\](:(\d+)x(\d+))?$#', $path, $matches);
    }
}
