<?php

namespace SaboCore\Utils\FileManager;

use Override;
use SaboCore\Routing\Response\DownloadResponse;
use SaboCore\treatment\TreatmentException;
use SaboCore\Utils\Storage\AppStorage;

/**
 * @brief Gestionnaire de fichier provenant de formulaire ($_FILES)
 * @author yahaya bathily
 */
class FormFileManager extends FileManager{
    /**
     * @var array Données du fichier au format dans $_FILES
     */
    protected array $fileDatas;

    public function __construct(array $fileDatas){
        parent::__construct($fileDatas["tmp_name"]);

        $this->fileDatas = $fileDatas;
    }

    /**
     * @inheritDoc
     * @attention fonction inactive pour les fichiers de formulaire, passer par FileManager
     */
    #[Override]
    public function getToDownload(): DownloadResponse{
        throw new TreatmentException("Ce fichier ne peut pas être téléchargé",true);
    }

    #[Override]
    public function storeIn(string $path, bool $createFoldersIfNotExists = true): bool{
        return $this->getErrorState() == 0 && AppStorage::storeFormFile($path,$this->fileAbsolutePath,$createFoldersIfNotExists);
    }

    /**
     * @inheritDoc
     * @attention fonction inactive pour les fichiers de formulaire, passer par FileManager
     */
    #[Override]
    public function getFromStorage(): ?FileContentManager{
        return null;
    }

    /**
     * @return string le type mime contenu du fichier
     */
    public function getType():string{
        return $this->fileDatas["type"] ?? "";
    }

    /**
     * @brief Vérifie si le type mime contenu du fichier est présent dans la liste fournie
     * @param string ...$typesToCheck
     * @return bool
     */
    public function isInTypes(string ...$typesToCheck):bool{
        return in_array($this->getType(),$typesToCheck);
    }

    /**
     * @return int l'état de l'erreur contenue
     */
    public function getErrorState():int{
        return $this->fileDatas["error"];
    }

    /**
     * @return int la taille contenu
     */
    public function getSize():int{
        return $this->fileDatas["size"];
    }
}