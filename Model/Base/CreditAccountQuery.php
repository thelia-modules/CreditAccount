<?php

namespace CreditAccount\Model\Base;

use \Exception;
use \PDO;
use CreditAccount\Model\CreditAccount as ChildCreditAccount;
use CreditAccount\Model\CreditAccountQuery as ChildCreditAccountQuery;
use CreditAccount\Model\Map\CreditAccountTableMap;
use CreditAccount\Model\Thelia\Model\Customer;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'credit_account' table.
 *
 *
 *
 * @method     ChildCreditAccountQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCreditAccountQuery orderByAmount($order = Criteria::ASC) Order by the amount column
 * @method     ChildCreditAccountQuery orderByCustomerId($order = Criteria::ASC) Order by the customer_id column
 * @method     ChildCreditAccountQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCreditAccountQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildCreditAccountQuery groupById() Group by the id column
 * @method     ChildCreditAccountQuery groupByAmount() Group by the amount column
 * @method     ChildCreditAccountQuery groupByCustomerId() Group by the customer_id column
 * @method     ChildCreditAccountQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCreditAccountQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildCreditAccountQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCreditAccountQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCreditAccountQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCreditAccountQuery leftJoinCustomer($relationAlias = null) Adds a LEFT JOIN clause to the query using the Customer relation
 * @method     ChildCreditAccountQuery rightJoinCustomer($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Customer relation
 * @method     ChildCreditAccountQuery innerJoinCustomer($relationAlias = null) Adds a INNER JOIN clause to the query using the Customer relation
 *
 * @method     ChildCreditAccountQuery leftJoinCreditAccountExpiration($relationAlias = null) Adds a LEFT JOIN clause to the query using the CreditAccountExpiration relation
 * @method     ChildCreditAccountQuery rightJoinCreditAccountExpiration($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CreditAccountExpiration relation
 * @method     ChildCreditAccountQuery innerJoinCreditAccountExpiration($relationAlias = null) Adds a INNER JOIN clause to the query using the CreditAccountExpiration relation
 *
 * @method     ChildCreditAccountQuery leftJoinCreditAmountHistory($relationAlias = null) Adds a LEFT JOIN clause to the query using the CreditAmountHistory relation
 * @method     ChildCreditAccountQuery rightJoinCreditAmountHistory($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CreditAmountHistory relation
 * @method     ChildCreditAccountQuery innerJoinCreditAmountHistory($relationAlias = null) Adds a INNER JOIN clause to the query using the CreditAmountHistory relation
 *
 * @method     ChildCreditAccount findOne(ConnectionInterface $con = null) Return the first ChildCreditAccount matching the query
 * @method     ChildCreditAccount findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCreditAccount matching the query, or a new ChildCreditAccount object populated from the query conditions when no match is found
 *
 * @method     ChildCreditAccount findOneById(int $id) Return the first ChildCreditAccount filtered by the id column
 * @method     ChildCreditAccount findOneByAmount(double $amount) Return the first ChildCreditAccount filtered by the amount column
 * @method     ChildCreditAccount findOneByCustomerId(int $customer_id) Return the first ChildCreditAccount filtered by the customer_id column
 * @method     ChildCreditAccount findOneByCreatedAt(string $created_at) Return the first ChildCreditAccount filtered by the created_at column
 * @method     ChildCreditAccount findOneByUpdatedAt(string $updated_at) Return the first ChildCreditAccount filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildCreditAccount objects filtered by the id column
 * @method     array findByAmount(double $amount) Return ChildCreditAccount objects filtered by the amount column
 * @method     array findByCustomerId(int $customer_id) Return ChildCreditAccount objects filtered by the customer_id column
 * @method     array findByCreatedAt(string $created_at) Return ChildCreditAccount objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCreditAccount objects filtered by the updated_at column
 *
 */
abstract class CreditAccountQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \CreditAccount\Model\Base\CreditAccountQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\CreditAccount\\Model\\CreditAccount', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCreditAccountQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCreditAccountQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \CreditAccount\Model\CreditAccountQuery) {
            return $criteria;
        }
        $query = new \CreditAccount\Model\CreditAccountQuery();
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
     * @return ChildCreditAccount|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CreditAccountTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CreditAccountTableMap::DATABASE_NAME);
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
     * @return   ChildCreditAccount A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, AMOUNT, CUSTOMER_ID, CREATED_AT, UPDATED_AT FROM credit_account WHERE ID = :p0';
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
            $obj = new ChildCreditAccount();
            $obj->hydrate($row);
            CreditAccountTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildCreditAccount|array|mixed the result, formatted by the current formatter
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
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CreditAccountTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CreditAccountTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CreditAccountTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CreditAccountTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the amount column
     *
     * Example usage:
     * <code>
     * $query->filterByAmount(1234); // WHERE amount = 1234
     * $query->filterByAmount(array(12, 34)); // WHERE amount IN (12, 34)
     * $query->filterByAmount(array('min' => 12)); // WHERE amount > 12
     * </code>
     *
     * @param     mixed $amount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByAmount($amount = null, $comparison = null)
    {
        if (is_array($amount)) {
            $useMinMax = false;
            if (isset($amount['min'])) {
                $this->addUsingAlias(CreditAccountTableMap::AMOUNT, $amount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($amount['max'])) {
                $this->addUsingAlias(CreditAccountTableMap::AMOUNT, $amount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountTableMap::AMOUNT, $amount, $comparison);
    }

    /**
     * Filter the query on the customer_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomerId(1234); // WHERE customer_id = 1234
     * $query->filterByCustomerId(array(12, 34)); // WHERE customer_id IN (12, 34)
     * $query->filterByCustomerId(array('min' => 12)); // WHERE customer_id > 12
     * </code>
     *
     * @see       filterByCustomer()
     *
     * @param     mixed $customerId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByCustomerId($customerId = null, $comparison = null)
    {
        if (is_array($customerId)) {
            $useMinMax = false;
            if (isset($customerId['min'])) {
                $this->addUsingAlias(CreditAccountTableMap::CUSTOMER_ID, $customerId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerId['max'])) {
                $this->addUsingAlias(CreditAccountTableMap::CUSTOMER_ID, $customerId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountTableMap::CUSTOMER_ID, $customerId, $comparison);
    }

    /**
     * Filter the query on the created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByCreatedAt('2011-03-14'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt('now'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt(array('max' => 'yesterday')); // WHERE created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $createdAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CreditAccountTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CreditAccountTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountTableMap::CREATED_AT, $createdAt, $comparison);
    }

    /**
     * Filter the query on the updated_at column
     *
     * Example usage:
     * <code>
     * $query->filterByUpdatedAt('2011-03-14'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt('now'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt(array('max' => 'yesterday')); // WHERE updated_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $updatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CreditAccountTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CreditAccountTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CreditAccountTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \CreditAccount\Model\Thelia\Model\Customer object
     *
     * @param \CreditAccount\Model\Thelia\Model\Customer|ObjectCollection $customer The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByCustomer($customer, $comparison = null)
    {
        if ($customer instanceof \CreditAccount\Model\Thelia\Model\Customer) {
            return $this
                ->addUsingAlias(CreditAccountTableMap::CUSTOMER_ID, $customer->getId(), $comparison);
        } elseif ($customer instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CreditAccountTableMap::CUSTOMER_ID, $customer->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCustomer() only accepts arguments of type \CreditAccount\Model\Thelia\Model\Customer or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Customer relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function joinCustomer($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Customer');

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
            $this->addJoinObject($join, 'Customer');
        }

        return $this;
    }

    /**
     * Use the Customer relation Customer object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \CreditAccount\Model\Thelia\Model\CustomerQuery A secondary query class using the current class as primary query
     */
    public function useCustomerQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCustomer($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Customer', '\CreditAccount\Model\Thelia\Model\CustomerQuery');
    }

    /**
     * Filter the query by a related \CreditAccount\Model\CreditAccountExpiration object
     *
     * @param \CreditAccount\Model\CreditAccountExpiration|ObjectCollection $creditAccountExpiration  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByCreditAccountExpiration($creditAccountExpiration, $comparison = null)
    {
        if ($creditAccountExpiration instanceof \CreditAccount\Model\CreditAccountExpiration) {
            return $this
                ->addUsingAlias(CreditAccountTableMap::ID, $creditAccountExpiration->getCreditAccountId(), $comparison);
        } elseif ($creditAccountExpiration instanceof ObjectCollection) {
            return $this
                ->useCreditAccountExpirationQuery()
                ->filterByPrimaryKeys($creditAccountExpiration->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCreditAccountExpiration() only accepts arguments of type \CreditAccount\Model\CreditAccountExpiration or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CreditAccountExpiration relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function joinCreditAccountExpiration($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CreditAccountExpiration');

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
            $this->addJoinObject($join, 'CreditAccountExpiration');
        }

        return $this;
    }

    /**
     * Use the CreditAccountExpiration relation CreditAccountExpiration object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \CreditAccount\Model\CreditAccountExpirationQuery A secondary query class using the current class as primary query
     */
    public function useCreditAccountExpirationQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCreditAccountExpiration($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CreditAccountExpiration', '\CreditAccount\Model\CreditAccountExpirationQuery');
    }

    /**
     * Filter the query by a related \CreditAccount\Model\CreditAmountHistory object
     *
     * @param \CreditAccount\Model\CreditAmountHistory|ObjectCollection $creditAmountHistory  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function filterByCreditAmountHistory($creditAmountHistory, $comparison = null)
    {
        if ($creditAmountHistory instanceof \CreditAccount\Model\CreditAmountHistory) {
            return $this
                ->addUsingAlias(CreditAccountTableMap::ID, $creditAmountHistory->getCreditAccountId(), $comparison);
        } elseif ($creditAmountHistory instanceof ObjectCollection) {
            return $this
                ->useCreditAmountHistoryQuery()
                ->filterByPrimaryKeys($creditAmountHistory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCreditAmountHistory() only accepts arguments of type \CreditAccount\Model\CreditAmountHistory or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CreditAmountHistory relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function joinCreditAmountHistory($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CreditAmountHistory');

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
            $this->addJoinObject($join, 'CreditAmountHistory');
        }

        return $this;
    }

    /**
     * Use the CreditAmountHistory relation CreditAmountHistory object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \CreditAccount\Model\CreditAmountHistoryQuery A secondary query class using the current class as primary query
     */
    public function useCreditAmountHistoryQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCreditAmountHistory($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CreditAmountHistory', '\CreditAccount\Model\CreditAmountHistoryQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCreditAccount $creditAccount Object to remove from the list of results
     *
     * @return ChildCreditAccountQuery The current query, for fluid interface
     */
    public function prune($creditAccount = null)
    {
        if ($creditAccount) {
            $this->addUsingAlias(CreditAccountTableMap::ID, $creditAccount->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the credit_account table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CreditAccountTableMap::DATABASE_NAME);
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
            CreditAccountTableMap::clearInstancePool();
            CreditAccountTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCreditAccount or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCreditAccount object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CreditAccountTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CreditAccountTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CreditAccountTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CreditAccountTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ChildCreditAccountQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CreditAccountTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildCreditAccountQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CreditAccountTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildCreditAccountQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CreditAccountTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildCreditAccountQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CreditAccountTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildCreditAccountQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CreditAccountTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildCreditAccountQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CreditAccountTableMap::CREATED_AT);
    }

} // CreditAccountQuery
