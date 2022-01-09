<?php

namespace Lle\PdfGeneratorBundle\Lib;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfArchive extends Fpdi
{
    const ICC_PROFILE_PATH = __DIR__ . "/icc/profile.icc";

    /** @var $attachments array */
    protected $attachments = array();

    /** @var $metadata_xmp array */
    protected $metadata_xmp = array();

    /** @var int $description_index */
    protected $description_index = 0;

    /** @var int $output_intent_index */
    protected $output_intent_index = 0;

    /** @var int $n_files */
    protected $n_files;

    /** @var \DateTime $createdAt */
    protected $createdAt;

    /** @var string $part */
    protected $part;

    /** @var string $conformance */
    protected $conformance;

    public function __construct(
        $orientation = 'P',
        $unit = 'mm',
        $size = 'A4',
        $version = "1.7",
        $part = "3",
        $conformance = "B"
    ) {
        parent::__construct($orientation, $unit, $size);
        $this->createdAt = new \DateTime();
        $this->PDFVersion = sprintf('%.1F', $version);
        $this->part = $part;
        $this->conformance = $conformance;
    }

    /**
     * @param StreamReader $file
     * @param string $name
     * @param string $desc
     * @param string $relationship
     * @param string $mimetype
     */
    public function attachStreamReader(
        StreamReader $file,
                     $name = "",
                     $desc = "",
                     $relationship = "Alternative",
                     $mimetype = "text#2Fxml"
    ) {
        $this->attachments[] = [
            'file' => $file,
            'name' => $name,
            'desc' => $desc,
            'relationship' => $relationship,
            'subtype' => $mimetype
        ];
    }

    public function getFormattedCreatedAt(): string
    {
        $date = $this->createdAt->format('YmdHisO');
        return 'D:'.substr($date,0,-2)."'".substr($date,-2)."'";
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function addXMLMetadata(string $xmlMetadata)
    {
        $this->metadata_xmp[] = $xmlMetadata;
    }

    /**
     * @return string
     */
    public function getPart()
    {
        return $this->part;
    }

    /**
     * @return string
     */
    public function getConformance()
    {
        return $this->conformance;
    }

    protected function _put_files()
    {
        foreach ($this->attachments as $i => &$info) {
            $this->_put_file_specification($info);
            $info['file_index'] = $this->n;
            $this->_put_file_stream($info);
        }
        $this->_put_file_dictionary();
    }

    protected function _put_file_stream(array $file_info)
    {
        $this->_newobj();
        $this->_put('<<');
        //filtre de décompression
        $this->_put('/Filter /FlateDecode');
        if ($file_info['subtype']) {
            $this->_put('/Subtype /'.$file_info['subtype']);
        }
        $this->_put('/Type /EmbeddedFile');
         $md = date("now");
        if (is_string($file_info['file']) && @is_file($file_info['file'])) {
            $fc = file_get_contents($file_info['file']);
            $md = @date('YmdHis', filemtime($file_info['file']));
        } else {
            $stream = $file_info['file']->getStream();
            \fseek($stream, 0);
            $fc = stream_get_contents($stream);
        }
        
        if (false === $fc) {
            $this->Error('Cannot open file: '.$file_info['file']);
        }
        //compression du contenu
        $fc = gzcompress($fc);
        $this->_put('/Length '.strlen($fc));
        $this->_put("/Params <</ModDate (D:$md)>>");
        $this->_put('>>');
        $this->_putstream($fc);
        $this->_put('endobj');
    }

    protected function _put_file_specification(array $file_info)
    {
        $this->_newobj();
        $this->file_spe_dictionnary_index = $this->n;
        $this->_put('<<');
        $this->_put('/F ('.$this->_escape($file_info['name']).')');
        $this->_put('/Type /Filespec');
        $this->_put('/UF '.$this->_textstring(utf8_encode($file_info['name'])));
        if ($file_info['relationship']) {
            $this->_put('/AFRelationship /'.$file_info['relationship']);
        }
        if ($file_info['desc']) {
            $this->_put('/Desc '.$this->_textstring($file_info['desc']));
        }
        $this->_put('/EF <<');
        $this->_put('/F '.($this->n + 1).' 0 R');
        $this->_put('/UF '.($this->n + 1).' 0 R');
        $this->_put('>>');
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _put_file_dictionary()
    {
        $this->_newobj();
        $this->n_files = $this->n;
        $this->_put('<<');
        $s = '';
        $files = $this->attachments;
        usort($files, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        foreach ($files as $info) {
            $s .= sprintf('%s %s 0 R ', $this->_textstring($info['name']), $info['file_index']);
        }
        $this->_put(sprintf('/Names [%s]', $s));
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _put_metadata()
    {
        $s = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'."\n";
        $s .= '<x:xmpmeta xmlns:x="adobe:ns:meta/">'."\n";
        $this->_newobj();
        $this->description_index = $this->n;
        foreach ($this->metadata_xmp as $i => $desc) {
            $s .= $desc."\n";
        }
        $s .= '</x:xmpmeta>'."\n";
        $s .= '<?xpacket end="w"?>';
        $this->_put('<<');
        $this->_put('/Length '.strlen($s));
        $this->_put('/Type /Metadata');
        $this->_put('/Subtype /XML');
        $this->_put('>>');
        $this->_putstream($s);
        $this->_put('endobj');
    }


    protected function _putcolorprofile()
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Type /OutputIntent');
        $this->_put('/S /GTS_PDFA1');
        $this->_put('/OuputCondition (sRGB)');
        $this->_put('/OutputConditionIdentifier (Custom)');
        $this->_put('/DestOutputProfile '.($this->n + 1).' 0 R');
        $this->_put('/Info (sRGB V4 ICC)');
        $this->_put('>>');
        $this->_put('endobj');
        $this->output_intent_index = $this->n;

        $icc = file_get_contents($this::ICC_PROFILE_PATH);
        $icc = gzcompress($icc);
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Length '.strlen($icc));
        $this->_put('/N 3');
        $this->_put('/Filter /FlateDecode');
        $this->_put('>>');
        $this->_putstream($icc);
        $this->_put('endobj');
    }

    protected function _putresources()
    {
        parent::_putresources();
        if (!empty($this->attachments)) {
            $this->_put_files();
        }
        $this->_putcolorprofile();
        if (!empty($this->metadata_xmp)) {
            $this->_put_metadata();
        }
    }

    protected function _putcatalog()
    {
        parent::_putcatalog();
        if (!empty($this->attachments)) {
            if (is_array($this->attachments)) {
                $files_ref_str = '';
                foreach ($this->attachments as $file) {
                    if ('' != $files_ref_str) {
                        $files_ref_str .= ' ';
                    }
                    $files_ref_str .= sprintf('%s 0 R', $file['file_index']);
                }
                $this->_put(sprintf('/AF [%s]', $files_ref_str));
            } else {
                $this->_put(sprintf('/AF %s 0 R', $this->n_files));
            }
            if (0 != $this->description_index) {
                $this->_put(sprintf('/Metadata %s 0 R', $this->description_index));
            }
            $this->_put('/Names <<');
            $this->_put('/EmbeddedFiles ');
            $this->_put(sprintf('%s 0 R', $this->n_files));
            $this->_put('>>');
        }
        if (0 != $this->output_intent_index) {
            $this->_put(sprintf('/OutputIntents [%s 0 R]', $this->output_intent_index));
        }
        if (count($this->attachments) > 0) {
            $this->_put('/PageMode /UseAttachments');
        }
    }

    protected function _puttrailer()
    {
        parent::_puttrailer();
        $created_id = md5((new \DateTime())->format("YmdHis"));
        $modified_id = md5((new \DateTime())->format("YmdHis"));
        $this->_put(sprintf('/ID [<%s><%s>]', $created_id, $modified_id));
    }

    /**
     * Redéfini la méthode _putheader
     */
    protected function _putheader()
    {
        parent::_putheader();
        $this->_put("%\xE2\xE3\xCF\xD3");
    }

}
