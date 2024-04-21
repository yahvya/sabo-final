<?php

namespace SaboCore\Database\Default\QueryBuilder;
require_once("../../../vendor/autoload.php");
use PDO;
use PDOStatement;
use ReflectionClass;
use SaboCore\Config\ConfigException;
use SaboCore\Database\Default\Attributes\DecimalColumn;
use SaboCore\Database\Default\Attributes\IntColumn;
use SaboCore\Database\Default\Attributes\TableName;
use SaboCore\Database\Default\Attributes\VarcharColumn;
use SaboCore\Database\Default\System\MysqlFunction;
use SaboCore\Database\Default\System\MysqlModel;
use SaboCore\Database\Default\System\MysqlBindDatas;
use Throwable;

/**
 * @brief Constructeur de requête
 * @author yahaya bathily https://github.com/yahvya
 */
class MysqlQueryBuilder{
    /**
     * @var string Chaine sql de la requête
     */
    protected string $sqlString;

    /**
     * @var MysqlBindDatas[] Valeur à bind
     */
    protected array $toBind;

    /**
     * @var MysqlModel Model de base
     */
    protected MysqlModel $baseModel;

    /**
     * @var string Alias de la table
     */
    protected string $tableAlias;

    /**
     * @param string $modelClass class du model
     * @throws ConfigException en cas d'erreur
     */
    public function __construct(string $modelClass){
        try{
            $reflection = new ReflectionClass(objectOrClass: $modelClass);

            $model = $reflection->newInstance();

            if(!($model instanceof MysqlModel))
                throw new ConfigException(message: "La class fournie doit être une sous class de " . MysqlModel::class);

            $this->baseModel = $model;
            $this->reset();
        }
        catch(Throwable){
            throw new ConfigException(message: "Une erreur s'est produite lors de la construction du builder");
        }
    }

    /**
     * @brief Démarre une requête statique
     * @param string $sqlString requête sql
     * @param array $toBind Valeur à bind
     * @return $this
     */
    public function staticRequest(string $sqlString,array $toBind = []):MysqlQueryBuilder{
        $this->sqlString = $sqlString;
        $this->toBind = $toBind;

        return $this;
    }

    /**
     * @brief Remet à 0 le contenu du QueryBuilder
     * @return $this
     */
    public function reset():MysqlQueryBuilder{
        $this->sqlString = "";
        $this->toBind = [];
        $this->tableAlias = $this->baseModel->getTableNameManager()->getTableName() . time();

        return $this;
    }

    /**
     * @brief Prépare la requête
     * @param PDO $pdo instance pdo
     * @return PDOStatement|null Résultat de la préparation
     */
    public function prepareRequest(PDO $pdo):?PDOStatement{
        try{
            return $pdo->prepare("");
        }
        catch(Throwable){
            return null;
        }
    }

    /**
     * @brief Met à jour l'alias de la table
     * @param string $alias nouvel alias
     * @return $this
     */
    public function as(string $alias):MysqlQueryBuilder{
        $this->tableAlias = $alias;

        return $this;
    }

    /**
     * @return string La chaine sql sans modification
     * @attention La chaine fournie peut ne pas être utilisable
     */
    public function getRealSql():string{
        return $this->sqlString;
    }

    /**
     * @return string La chaine sql formaté pour une requête
     */
    public function getSql():string{
        return str_replace(
            search: ["{aliasTable}"],
            replace: [$this->tableAlias],
            subject: $this->sqlString
        );
    }

    /**
     * @return MysqlBindDatas[] les valeurs à bind
     */
    public function getBindValues():array{
        return $this->toBind;
    }

    /**
     * @brief Fonctions de requêtage
     */

    /**
     * @brief Ajoute la chaine SELECT [] FROM table
     * @param string|MysqlFunction ...$toSelect
     * @return $this
     * @attention en fonction des champs sélectionnés le / les models générés seront partiellement construit s'il manque des champs.
     * @throws Throwable en cas d'erreur
     */
    public function select(string|MysqlFunction ...$toSelect):MysqlQueryBuilder{
        $this->sqlString .= "SELECT ";

        $tableColumnsConfig = $this->baseModel->getColumnsConfig();

        $columnsToSelect = [];

        // remplacement des données à sélectionner
        foreach($toSelect as $value){
            if(gettype($value) === "string"){
                $columnsToSelect[] = $tableColumnsConfig[$value]->getColumnName();
                continue;
            }

            // traitement de la "fonction"
            $alias = $value->getAlias();
            $function = $value->getFunction();

            if($value->haveToReplaceAttributesName()){
                // remplacement des valeurs
                foreach($tableColumnsConfig as $attributeNameToReplace => $attributeConfig)
                    $function = @str_replace(search: "{{$attributeNameToReplace}}",replace: $attributeConfig->getColumnName(),subject: $function);
            }

            // traitement de l'alias et ajout dans la liste des colonnes sélectionnées
            $columnsToSelect[] = $function . ($alias ? " AS $alias" : "");
        }

        $this->sqlString .= (empty($columnsToSelect) ? "*" : implode(separator: ",",array: $columnsToSelect)) . " FROM {$this->baseModel->getTableNameManager()->getTableName()} AS {aliasTable} ";

        return $this;
    }

    /**
     * @brief Ajoute la chaine UPDATE table SET []
     * @param MysqlBindDatas[] $updateConfig Tableau indicé par le nom des attributs à changer et avec valeur l'instance MysqlBindDatas
     */
    public function update(array $updateConfig):MysqlQueryBuilder{
        $this->sqlString .= "UPDATE {$this->baseModel->getTableNameManager()->getTableName()} AS {tableAlias} SET ";        

        $columnsConfig = $this->baseModel->getColumnsConfig();
        $columnsToUpdate = [];

        // ajout des attributs à modifier
        foreach($updateConfig as $attributeName => $bindConfig){

        }

        return $this;
    }

    public function delete():MysqlQueryBuilder{
        return $this;        
    }
}

#[TableName(tableName: "users")]
class UserModel extends MysqlModel{
    #[IntColumn(columnName: "id", isAutoIncrement: true, isPrimaryKey: true)]
    protected int $id;

    #[VarcharColumn(columnName: "username",maxLen: 255)]
    protected string $name;

    #[DecimalColumn(columnName: "payed-price")]
    protected float $price;
}

$queryBuilder = new MysqlQueryBuilder(modelClass: UserModel::class);

$queryBuilder
    ->as(alias: "user-alias")
    ->select(
        "name",
        MysqlFunction::COUNT(numberGetter: "{price}"),
        MysqlFunction::COLUMN_ALIAS(attributeName: "name",alias: "name-alias"),
        MysqlFunction::DATE_FORMAT("'2023-10-20'","%Y")
    );

var_dump($queryBuilder->getSql());

$queryBuilder
    ->reset()
    ->as(alias: "user-alias")
    ->update(updateConfig: [
        "price" => 300.3,
        "name" => "svel"
    ]);

var_dump($queryBuilder->getSql());