<?php

namespace SaboCore\Database\Default\Cond;

use Attribute;
use Override;

/**
 * @brief Attribut définissant une condition regex
 * @author yahaya bathily https://github.com/yahvya
 */
#[Attribute]
class RegexCond implements Cond{
    /**
     * @var string Message d'erreur
     */
    private string $errorMessage;

    /**
     * @var string Regex à vérifier
     */
    private string $regex;

    /**
     * @var string Options supplémentaires de la regex
     */
    private string $regexOptions;

    /**
     * @var string Délimiteurs de la regex
     */
    private string $delimiter;

    /**
     * @param string $regex la chaine regex
     * @param string $errorMessage le message d'erreur en cas de non validation
     * @param string $regexOptions les options à ajouter sur la regex
     * @param string $delimiter le délimiteur de la regex (1 caractère)
     */
    public function __construct(string $regex,string $errorMessage,string $regexOptions = "",string $delimiter = "#"){
        $this->regex = $regex;
        $this->errorMessage = $errorMessage;
        $this->regexOptions = $regexOptions;
        $this->delimiter = strlen($delimiter) == 1 ? $delimiter : "#";
    }

    #[Override]
    public function checkCondWith(mixed $data):bool{
        return @preg_match($this->delimiter . $this->regex . $this->delimiter . $this->regexOptions,$data);
    }

    #[Override]
    public function getIsDisplayable():bool{
        return true;
    }

    #[Override]
    public function getErrorMessage():string{
        return $this->errorMessage;
    }
}