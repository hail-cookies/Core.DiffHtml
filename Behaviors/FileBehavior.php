<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * Makes the object behave as a file regardless of its data source (e.g. files in DB, etc.)
 * 
 * Many features like the `DataSourceFileConnector` required objects, that represent files to have
 * this behavior. Others may work even without the behavior, but will require much more configuration:
 * e.g. widgets `ImageGallery`, `FileList`, etc.
 * 
 * For use in external libaries, there is an adapter for the `splFileinfo` class, that allows PHP code
 * to use items from an object with `FileBehavior` as files: `DataSourceFileInfo`.
 * 
 * @author Andrej Kabachnik
 *
 */
class FileBehavior extends AbstractBehavior
{    
    private $filenameAttributeAlias = null;
    
    private $contentsAttributeAlias = null;
    
    private $mimeTypeAttributeAlias = null;
    
    private $fileSizeAttributeAlias = null;
    
    private $timeCreatedAttributeAlias = null;
    
    private $timeModifiedAttributeAlias = null;
    
    private $maxFileSizeMb = null;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFilenameAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->filenameAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the full filename (incl. extension)
     * 
     * @uxon-property filename_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return FileBehavior
     */
    protected function setFilenameAttribute(string $value) : FileBehavior
    {
        $this->filenameAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getContentsAttribute() : MetaAttributeInterface
    {
        return $this->getObject()->getAttribute($this->contentsAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the file contents
     * 
     * @uxon-property contents_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return FileBehavior
     */
    protected function setContentsAttribute(string $value) : FileBehavior
    {
        $this->contentsAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getMimeTypeAttribute() : ?MetaAttributeInterface
    {
        return $this->mimeTypeAttributeAlias === null ? null : $this->getObject()->getAttribute($this->mimeTypeAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that represents the mime type of the file
     *
     * @uxon-property mime_type_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setMimeTypeAttribute(string $value) : FileBehavior
    {
        $this->mimeTypeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getFileSizeAttribute() : ?MetaAttributeInterface
    {
        return $this->fileSizeAttributeAlias === null ? null : $this->getObject()->getAttribute($this->fileSizeAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the size of the file in bytes (optional)
     *
     * @uxon-property file_size_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setFileSizeAttribute(string $value) : FileBehavior
    {
        $this->fileSizeAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return MetaAttributeInterface|NULL
     */
    public function getTimeCreatedAttribute() : ?MetaAttributeInterface
    {
        return $this->timeCreatedAttributeAlias === null ? null : $this->getObject()->getAttribute($this->timeCreatedAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the creation time of the file (optional)
     *
     * @uxon-property time_created_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setTimeCreatedAttribute(string $value) : FileBehavior
    {
        $this->timeCreatedAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return MetaAttributeInterface|NULL
     */
    public function getTimeModifiedAttribute() : ?MetaAttributeInterface
    {
        return $this->timemo === null ? null : $this->getObject()->getAttribute($this->timeModifiedAttributeAlias);
    }
    
    /**
     * Alias of the attribute, that contains the modification time of the file (optional)
     *
     * @uxon-property time_modified_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return FileBehavior
     */
    protected function setTimeModifiedAttribute(string $value) : FileBehavior
    {
        $this->timeModifiedAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getMaxFileSizeInMb() : ?float
    {
        return $this->maxFileSizeMb;
    }
    
    /**
     * Maximum allowed file size in MB
     * 
     * @uxon-property max_file_size_in_mb
     * @uxon-type number
     * 
     * @param float $value
     * @return FileBehavior
     */
    protected function setMaxFileSizeInMb(float $value) : FileBehavior
    {
        $this->maxFileSizeMb = $value;
        return $this;
    }
    
    /**
     * 
     * @return MetaAttributeInterface[]
     */
    public function getFileAttributes() : array
    {
        $attrs = [];
        if (null !== $attr = $this->getFilenameAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getContentsAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getFileSizeAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getMimeTypeAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getTimeCreatedAttribute()) {
            $attrs[] = $attr;
        }
        if (null !== $attr = $this->getTimeModifiedAttribute()) {
            $attrs[] = $attr;
        }
        return $attrs;
    }
}