<?php

namespace CreditAccount\Model\Base;

use \Exception;
use \PDO;
use CreditAccount\Model\CreditAccountExpiration as ChildCreditAccountExpiration;
use CreditAccount\Model\CreditAccountExpirationQuery as ChildCreditAccountExpirationQuery;
use CreditAccount\Model\Map\CreditAccountExpirationTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'credit_account_expiration' table.
 *
 *
 *
 * @method     ChildCreditAccountExpirationQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCreditAccountExpirationQuery orderByCreditAccountId($order = Criteria::ASC) Order by the credit_account_id column
 * @method     ChildCreditAccountExpirationQuery orderByExpirationStart($order = Criteria::ASC) Order by the expiration_start column
 * @method     ChildCreditAccountExpirationQuery orderByExpirationDelay($order = Criteria::ASC) Order by the expiration_delay column
 *
 * @method     ChildCreditAccountExpirationQuery groupById() Group by the id column
 * @method     ChildCreditAccountExpirationQuery groupByCreditAccountId() Group by the credit_account_id column
 * @method     ChildCreditAccountExpirationQuery groupByExpirationStart() Group by the expiration_start column
 * @method     ChildCreditAccountExpirationQuery groupByExpirationDelay() Group by the expiration_delay column
 *
 * @method     ChildCreditAccountExpirationQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCreditAccountExpirationQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCreditAccountExpirationQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCreditAccountExpirationQuery leftJoinCreditAccount($relationAlias = null) Adds a LEFT JOIN clause to the query using the CreditAccount relation
 * @method     ChildCreditAccountExpirationQuery rightJoinCreditAccount($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CreditAccount relation
 * @method     ChildCreditAccountExpirationQuery innerJoinCreditAccount($relationAlias = null) Adds a INNER JOIN clause to the query using the CreditAccount relation
 *
 * @method     ChildCreditAccountExpiration findOne(ConnectionInterface $con = null) Return the first ChildCreditAccountExpiration matching the query
 * @method     ChildCreditAccountExpiration findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCreditAccountExpiration matching the query, or a new ChildCreditAccountExpiration object populated from the query conditions when no match is found
 *
 * @method     ChildCreditAccountExpiration findOneById(int $id) Return the first ChildCreditAccountExpiration filtered by the id column
 * @method     ChildCreditAccountExpiration findOneByCreditAccountId(int $credit_account_id) Return the first ChildCreditAccountExpiration filtered by the credit_account_id column
 * @method     ChildCreditAccountExpiration findOneByExpirationStart(string $expiration_start) Return the first ChildCreditAccountExpiration filtered by the expiration_start column
 * @method     ChildCreditAccountExpiration findOneByExpirationDelay(int $expiration_delay) Return the first ChildCreditAccountExpiration filtered by the expiration_delay column
 *
 * @method     array findById(int $id) Return ChildCreditAccountExpiration objects filtered by the id column
 * @method     array findByCreditAccountId(int $credit_account_id) Return ChildCreditAccountExpiration objects filtered by the credit_account_id column
 * @method     array findByExpirationStart(string $expiration_start) Return ChildCreditAccountExpiration objects filtered by the expiration_start column
 * @method     array findByExpirationDelay(int $expiration_delay) Return ChildCreditAccountExpiration objects filtered by the expiration_delay column
 *
 */
abstract class CreditAccountExpirationQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \CreditAccount\Model\Base\CreditAccountExpirationQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\CreditAccount\\Model\\CreditAccountExpiration', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCreditAccountExpirationQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCreditAccountExpirationQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \CreditAccount\Model\CreditAccountExpirationQuery) {
            return $criteria;
        }
        $query = new \CreditAccount\Model\CreditAccountExpirationQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildCreditAccountExpiration|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CreditAccountExpirationTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CreditAccountExpirationTableMap::DATABASE_NAME);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return   ChildCreditAccountExpiration A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, CREDIT_ACCOUNT_ID, EXPIRATION_START, EXPIRATION_DELAY FROM credit_account_expiration WHERE ID = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildCreditAccountExpiration();
            $obj->hydrate($row);
            CreditAccountExpirationTableMap::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildCreditAccountExpiration|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CreditAccountExpirationTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CreditAccountExpirationTableMap::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountExpirationTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the credit_account_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCreditAccountId(1234); // WHERE credit_account_id = 1234
     * $query->filterByCreditAccountId(array(12, 34)); // WHERE credit_account_id IN (12, 34)
     * $query->filterByCreditAccountId(array('min' => 12)); // WHERE credit_account_id > 12
     * </code>
     *
     * @see       filterByCreditAccount()
     *
     * @param     mixed $creditAccountId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterByCreditAccountId($creditAccountId = null, $comparison = null)
    {
        if (is_array($creditAccountId)) {
            $useMinMax = false;
            if (isset($creditAccountId['min'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::CREDIT_ACCOUNT_ID, $creditAccountId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($creditAccountId['max'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::CREDIT_ACCOUNT_ID, $creditAccountId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountExpirationTableMap::CREDIT_ACCOUNT_ID, $creditAccountId, $comparison);
    }

    /**
     * Filter the query on the expiration_start column
     *
     * Example usage:
     * <code>
     * $query->filterByExpirationStart('2011-03-14'); // WHERE expiration_start = '2011-03-14'
     * $query->filterByExpirationStart('now'); // WHERE expiration_start = '2011-03-14'
     * $query->filterByExpirationStart(array('max' => 'yesterday')); // WHERE expiration_start > '2011-03-13'
     * </code>
     *
     * @param     mixed $expirationStart The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterByExpirationStart($expirationStart = null, $comparison = null)
    {
        if (is_array($expirationStart)) {
            $useMinMax = false;
            if (isset($expirationStart['min'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::EXPIRATION_START, $expirationStart['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($expirationStart['max'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::EXPIRATION_START, $expirationStart['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountExpirationTableMap::EXPIRATION_START, $expirationStart, $comparison);
    }

    /**
     * Filter the query on the expiration_delay column
     *
     * Example usage:
     * <code>
     * $query->filterByExpirationDelay(1234); // WHERE expiration_delay = 1234
     * $query->filterByExpirationDelay(array(12, 34)); // WHERE expiration_delay IN (12, 34)
     * $query->filterByExpirationDelay(array('min' => 12)); // WHERE expiration_delay > 12
     * </code>
     *
     * @param     mixed $expirationDelay The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterByExpirationDelay($expirationDelay = null, $comparison = null)
    {
        if (is_array($expirationDelay)) {
            $useMinMax = false;
            if (isset($expirationDelay['min'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::EXPIRATION_DELAY, $expirationDelay['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($expirationDelay['max'])) {
                $this->addUsingAlias(CreditAccountExpirationTableMap::EXPIRATION_DELAY, $expirationDelay['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountExpirationTableMap::EXPIRATION_DELAY, $expirationDelay, $comparison);
    }

    /**
     * Filter the query by a related \CreditAccount\Model\CreditAccount object
     *
     * @param \CreditAccount\Model\CreditAccount|ObjectCollection $creditAccount The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function filterByCreditAccount($creditAccount, $comparison = null)
    {
        if ($creditAccount instanceof \CreditAccount\Model\CreditAccount) {
            return $this
                ->addUsingAlias(CreditAccountExpirationTableMap::CREDIT_ACCOUNT_ID, $creditAccount->getId(), $comparison);
        } elseif ($creditAccount instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CreditAccountExpirationTableMap::CREDIT_ACCOUNT_ID, $creditAccount->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCreditAccount() only accepts arguments of type \CreditAccount\Model\CreditAccount or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CreditAccount relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function joinCreditAccount($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CreditAccount');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'CreditAccount');
        }

        return $this;
    }

    /**
     * Use the CreditAccount relation CreditAccount object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \CreditAccount\Model\CreditAccountQuery A secondary query class using the current class as primary query
     */
    public function useCreditAccountQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCreditAccount($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CreditAccount', '\CreditAccount\Model\CreditAccountQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCreditAccountExpiration $creditAccountExpiration Object to remove from the list of results
     *
     * @return ChildCreditAccountExpirationQuery The current query, for fluid interface
     */
    public function prune($creditAccountExpiration = null)
    {
        if ($creditAccountExpiration) {
            $this->addUsingAlias(CreditAccountExpirationTableMap::ID, $creditAccountExpiration->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the credit_account_expiration table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CreditAccountExpirationTableMap::DATABASE_NAME);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            CreditAccountExpirationTableMap::clearInstancePool();
            CreditAccountExpirationTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCreditAccountExpiration or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCreditAccountExpiration object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public function delete(ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CreditAccountExpirationTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CreditAccountExpirationTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CreditAccountExpirationTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CreditAccountExpirationTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // CreditAccountExpirationQuery
