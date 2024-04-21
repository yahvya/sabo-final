<?php

namespace SaboCore\Database\Default\QueryBuilder;
require_once("../../../vendor/autoload.php");
use PDO;
use PDOStatement;
use ReflectionClass;
use SaboCore\Config\ConfigException;
use SaboCore\Database\Default\Attributes\DecimalColumn;
use SaboCore\Database\Default\Attributes\IntColumn;
use SaboCore\Database\Default\Attributes\TableColumn;
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
     * @return MysqlModel instance du model de base
     */
    public function getBaseModel():MysqlModel{
        return $this->baseModel;
    }

    /**
     * @return MysqlBindDatas[] les valeurs à bind
     */
    public function getBindValues():array{
        return $this->toBind;
    }

    /**
     * @brief Join la requête fournie dans la requête actuelle
     * @param MysqlQueryBuilder $toJoin Builder à joindre
     * @param string|null $sqlBefore Chaine sql à placer à avant ou null
     * @param string|null $sqlAfter Chaine sql à placer après ou null
     * @return $this
     */
    public function joinBuilder(MysqlQueryBuilder $toJoin,?string $sqlBefore = null,?string $sqlAfter = null):MysqlQueryBuilder{
        $this->sqlString .= ($sqlBefore ?? "") . $toJoin->getSql() . ($sqlAfter ?? "");
        $this->toBind = array_merge($this->toBind,$toJoin->getBindValues());

        return $this;
    }

    /**
     * @brief Récupère les valeurs à bind en fonction de la valeur et fourni la chaine sql résultante
     * @attention Ne modifie pas la chaine sql
     * @param TableColumn $columnConfig Configuration de la colonne traitée
     * @param mixed|MysqlFunction|MysqlQueryBuilder $data le type de donnée à traiter
     * @param string|null $sqlBefore Chaine sql à placer avant en cas de Builder
     * @param string|null $sqlAfter Chaine sql à placer après en cas de Builder
     * @return array les données au format ["sql" => ...,"toBind" => [MysqlBindDatas, ...]
     */
    protected function manageValueDatas(TableColumn $columnConfig,mixed $data,?string $sqlBefore = null,?string $sqlAfter = null):array{
        if($data instanceof MysqlQueryBuilder){
            return [
                "sql" => ($sqlBefore ?? "") . $data->getSql() . ($sqlAfter ?? ""),
                "toBind" => $data->getBindValues()
             ];
        }
        else if($data instanceof MysqlFunction){
            // traitement de la "fonction"
            $alias = $data->getAlias();
            $function = $data->getFunction();

            if($data->haveToReplaceAttributesName())
                $function = $this->replaceAttributesNameIn(string: $function);

            return [
                // traitement de l'alias
                "sql" => $function . ($alias ? " AS $alias" : ""),
                "toBind" => []
            ];
        }
        else{
            $toBind = new MysqlBindDatas(
                countOfMarkers: 1,
                toBindDatas: [ [$data,$columnConfig->getColumnType()] ]
            );

            return [
                "sql" => $toBind->getMarkersStr(),
                "toBind" => [$toBind]
            ];
        }
    }

    /**
     * @brief Remplace le nom des attributs par le nom de colonne associé
     * @param string $string chaine à traiter
     * @return string résultat
     * @attention Le nom d'un attribut doit être placé entre {}
     */
    protected function replaceAttributesNameIn(string $string):string{
        $tableColumnsConfig = $this->baseModel->getColumnsConfig();

        // remplacement des valeurs
        foreach($tableColumnsConfig as $attributeNameToReplace => $attributeConfig)
            $string = @str_replace(search: "{{$attributeNameToReplace}}",replace: $attributeConfig->getColumnName(),subject: $string);

        return $string;
    }

    /**
     * @brief Fonctions de requêtes
     */

    /**
     * @brief Démarre une requête statique
     * @param string $sqlString requête sql
     * @param MysqlBindDatas[] $toBind Valeur à bind
     * @param bool $justConcat Si true concatène sinon remplace
     * @return $this
     */
    public function staticRequest(string $sqlString,array $toBind = [],bool $justConcat = false):MysqlQueryBuilder{
        if($justConcat){
            $this->sqlString .= $sqlString;
            $this->toBind = array_merge($this->toBind,$toBind);
        }
        else{
            $this->sqlString = $sqlString;
            $this->toBind = $toBind;
        }

        return $this;
    }

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

            if($value->haveToReplaceAttributesName())
                $function = $this->replaceAttributesNameIn(string: $function);

            // traitement de l'alias et ajout dans la liste des colonnes sélectionnées
            $columnsToSelect[] = $function . ($alias ? " AS $alias" : "");
        }

        $this->sqlString .= (empty($columnsToSelect) ? "*" : implode(separator: ",",array: $columnsToSelect)) . " FROM {$this->baseModel->getTableNameManager()->getTableName()} AS {aliasTable} ";

        return $this;
    }

    /**
     * @brief Ajoute la chaine UPDATE table SET []
     * @param MysqlFunction[]|MysqlQueryBuilder|mixed $updateConfig Tableau indicé par le nom des attributs à changer et avec valeur associé valeur|MysqlFunction|MysqlQueryBuilder
     * @return $this
     * @attention en cas de fonction ne pas y placer d'alias
     */
    public function update(array $updateConfig):MysqlQueryBuilder{
        $this->sqlString .= "UPDATE {$this->baseModel->getTableNameManager()->getTableName()} AS {aliasTable} SET ";

        $columnsConfig = $this->baseModel->getColumnsConfig();
        $columnsToUpdate = [];

        // ajout des attributs à modifier
        foreach($updateConfig as $attributeName => $newValue){
            ["sql" => $setSql,"toBind" => $valuesToBind] =  $this->manageValueDatas(
                columnConfig: $columnsConfig[$attributeName],
                data: $newValue,
                sqlBefore: "(",
                sqlAfter: ")"
            );

            $columnsToUpdate[$columnsConfig[$attributeName]->getColumnName()] = $setSql;

            $this->toBind = array_merge($this->toBind,$valuesToBind);
        }

        // construction du sql set
        foreach($columnsToUpdate as $columnName => $sql)
            $this->sqlString .= "$columnName = $sql, ";

        $this->sqlString = substr(string: $this->sqlString,offset: 0,length: -2) . " ";

        return $this;
    }

    /**
     * @brief Ajoute la chaine DELETE FROM table
     * @return $this
     */
    public function delete():MysqlQueryBuilder{
        $this->sqlString .= "DELETE FROM {$this->baseModel->getTableNameManager()->getTableName()} AS {aliasTable} ";

        return $this;        
    }

    /**
     * @brief Ajoute la clause limit
     * @param int $count Nombre d'éléments
     * @param int|null $offset Offset
     * @return $this
     */
    public function limit(int $count,?int $offset = null):MysqlQueryBuilder{
        if($offset == null){
            $this->sqlString .= "LIMIT ? ";
            $this->toBind[] = new MysqlBindDatas(
                countOfMarkers: 1,
                toBindDatas: [ [$count,PDO::PARAM_INT] ]
            );
        }
        else{
            $this->sqlString .= "LIMIT ? OFFSET ? ";
            $this->toBind[] = new MysqlBindDatas(
                countOfMarkers: 2,
                toBindDatas: [ [$count,PDO::PARAM_INT],[$offset,PDO::PARAM_INT] ]
            );
        }

        return $this;
    }
}

#[TableName(tableName: "users")]
class UserModel extends MysqlModel{
    #[IntColumn(columnName: "id", isAutoIncrement: true, isPrimaryKey: true)]
    protected int $id;

    #[VarcharColumn(columnName: "username",maxLen: 255)]
    protected string $name;

    #[DecimalColumn(columnName: "payed_price")]
    protected float $price;
}

$queryBuilder = new MysqlQueryBuilder(modelClass: UserModel::class);

var_dump($queryBuilder->delete()->getSql());