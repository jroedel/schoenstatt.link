<?php

declare(strict_types=1);

namespace SchoenstattTest\Db;

use SionModel\Db\Sql\Delete;
use SionModel\Db\Sql\Expression;
use SionModel\Db\Sql\Insert;
use SionModel\Db\Sql\Predicate\In;
use SionModel\Db\Sql\Predicate\IsNull;
use SionModel\Db\Sql\Predicate\Like;
use SionModel\Db\Sql\Predicate\Operator;
use SionModel\Db\Sql\Select;
use SionModel\Db\Sql\Statement;
use SionModel\Db\Sql\Update;
use SionModel\Db\Sql\Where;

/**
 * The corpus the SQL builder is recorded over.
 *
 * Every case is drawn from a statement the four repositories actually assemble — the
 * `ON DUPLICATE KEY` inserts and the other hand-written SQL are not here, because a builder
 * is not what writes them. What the corpus adds beyond the call sites is the *combinations*:
 * a nested group inside a nested group, an expression on both sides of a comparison, a
 * `WHERE` that mixes the array form with predicate objects. Those are where a rendering rule
 * that is nearly right stops being right, and no single call site exercises them.
 *
 * Each case is a closure so that a constructor that throws is a failure of the case and not
 * of collecting them.
 */
final class SqlBuilderCases
{
    /** @return array<string, callable(): Statement> */
    public static function all(): array
    {
        return self::selects() + self::writes();
    }

    /** @return array<string, callable(): Statement> */
    private static function selects(): array
    {
        return [
            'select: bare table' => static fn(): Select => new Select('sch_associations'),

            'select: columns, mixed aliasing' => static fn(): Select
                => (new Select('sch_publications'))->columns(['Title', 'Cat' => 'CategoryId']),

            'select: expression column' => static fn(): Select
                => (new Select('sch_visits'))->columns(['EntityId', 'TotalVisits' => new Expression('COUNT(*)')]),

            'select: group, having, expressions' => static fn(): Select
                => (new Select('sch_changes'))
                    ->columns([
                        'TheMonth' => new Expression('MONTH(`UpdatedOn`)'),
                        'TheYear'  => new Expression('YEAR(`UpdatedOn`)'),
                        'Count'    => new Expression('Count(*)'),
                    ])
                    ->group(['TheMonth', 'TheYear'])
                    ->having(['Count' => 3]),

            'select: order, plain list' => static fn(): Select
                => (new Select('relationships'))->order(['PredicateKind', 'Priority', 'UpdatedOn']),

            'select: order, direction map' => static fn(): Select
                => (new Select('trans_phrases'))
                    ->order(['text_domain' => 'ASC', 'phrase' => 'ASC', 'translation_phrase_id' => 'ASC']),

            'select: order, list and map mixed' => static fn(): Select
                => (new Select('lib_collections'))
                    ->order(['LibraryId', 'IsActive' => Select::ORDER_DESCENDING, 'CollectionName']),

            'select: order, one comma-separated string' => static fn(): Select
                => (new Select('sch_changes'))->order('TheYear, TheMonth'),

            'select: order reset' => static fn(): Select
                => (new Select('bib_dictionary'))->order(['KeyDe'])->reset(Select::ORDER),

            'select: limit and offset' => static fn(): Select
                => (new Select('sch_changes'))->order(['UpdatedOn' => 'DESC'])->limit(250)->offset(10),

            'select: join, string ON, aliased columns' => static fn(): Select
                => (new Select('sch_publications'))
                    ->columns(['Title'])
                    ->join(
                        'sch_pub_categories',
                        'sch_pub_categories.PublicationCategoryId = sch_publications.CategoryId',
                        ['SortOrder', 'CategoryName', 'CategoryParentId' => 'ParentId'],
                        Select::JOIN_LEFT
                    ),

            'select: join, both tables aliased, no columns' => static fn(): Select
                => (new Select(['l' => 'user_role_linker']))
                    ->join(['r' => 'user_role'], 'l.role_id = r.id', [])
                    ->where(['l.user_id' => 5, 'r.role_id' => 'admin']),

            'select: join on a predicate, column to column' => static function (): Select {
                $on = new Where();
                $on->addPredicates([
                    Operator::betweenColumns('relationships.SubjectEntityId', Operator::EQ, 'comments.CommentId'),
                    new Operator('relationships.PredicateKind', Operator::EQ, 'about'),
                ]);
                $on->addPredicate(new In('relationships.ObjectEntityId', [1, 2]));

                return (new Select('comments'))->join('relationships', $on, [], Select::JOIN_INNER);
            },

            'select: join keeps the parent table star' => static fn(): Select
                => (new Select('lib_books'))->join(
                    'lib_collections',
                    'lib_collections.CollectionId = lib_books.collection_id',
                    ['CollectionName', 'Abbreviation']
                ),

            'select: nested OR under AND, with a limit' => static function (): Select {
                $any = (new Where())->addPredicates(
                    [new Like('title', '%q%'), new Like('author', '%q%')],
                    Where::OP_OR
                );

                return (new Select('lib_books'))
                    ->where(new In('library_id', [1, 2]))
                    ->where($any)
                    ->order(['library_id', 'sort_text'])
                    ->limit(50);
            },

            'select: a group inside a group' => static function (): Select {
                $deep  = (new Where())->addPredicates(['a' => 1, 'b' => 2], Where::OP_OR);
                $inner = (new Where())->addPredicate($deep)->addPredicate(new IsNull('c'), Where::OP_OR);

                return (new Select('t'))->where(['d' => 4])->where($inner);
            },

            'select: every array-form conversion at once' => static fn(): Select
                => (new Select('sch_publications'))
                    ->where(['CategoryId' => 3, 'DataSource' => null, 'InLanguage' => ['de', 'es']]),

            'select: an expression as the compared value' => static fn(): Select
                => (new Select('sch_visits'))->where(['Total' => new Expression('COUNT(*)')]),

            'select: an expression as a whole condition' => static fn(): Select
                => (new Select('sch_visits'))
                    ->where([new Expression('`VisitedAt` >= DATE_ADD(NOW(), INTERVAL -1 MONTH)')]),

            'select: every comparison operator' => static function (): Select {
                $select = new Select('t');
                $operators = [
                    Operator::EQ,
                    Operator::NEQ,
                    Operator::LT,
                    Operator::LTE,
                    Operator::GT,
                    Operator::GTE,
                ];
                foreach ($operators as $operator) {
                    $select->where(new Operator('a', $operator, 1));
                }

                return $select;
            },

            'select: a backtick inside a name' => static fn(): Select
                => (new Select('t'))->where(['we`ird' => 1]),

            'select: a dotted column' => static fn(): Select => (new Select('p'))->where(['p.a' => 1]),

            'select: a condition written out in full' => static fn(): Select
                => (new Select('t'))->where(['`a` = `b`']),

            'select: columns reset' => static fn(): Select
                => (new Select('t'))->columns(['a'])->reset(Select::COLUMNS),
        ];
    }

    /** @return array<string, callable(): Statement> */
    private static function writes(): array
    {
        return [
            'insert: a null among bound values' => static fn(): Insert
                => (new Insert('sch_provenance'))->values([
                    'Entity'     => 'association',
                    'EntityId'   => 319,
                    'SourceUrl'  => null,
                    'RecordedOn' => '2026-09-22 10:00:00',
                    'RecordedBy' => 1,
                ]),

            'insert: an expression value' => static fn(): Insert
                => (new Insert('lib_checkouts'))
                    ->values(['PersonId' => 3, 'CheckedOutOn' => new Expression('UTC_TIMESTAMP()')]),

            'update: several columns' => static fn(): Update
                => (new Update('lib_checkouts'))
                    ->set(['DueOn' => '2026-10-01', 'TimesRenewed' => 2, 'LastRenewedOn' => '2026-09-22'])
                    ->where(['CheckoutId' => 7]),

            'update: a null and an expression' => static fn(): Update
                => (new Update('trans_phrases'))
                    ->set(['retired_on' => new Expression('UTC_TIMESTAMP()'), 'modified_by' => null])
                    ->where(['translation_phrase_id' => 4]),

            'update: an IN and a second term' => static fn(): Update
                => (new Update('trans_phrases'))
                    ->set(['retired_on' => '2026-09-22'])
                    ->where(['translation_phrase_id' => [1, 2, 3], 'project' => 'schoenstatt.link']),

            'delete: one term' => static fn(): Delete
                => (new Delete('trans_translations'))->where(['translation_id' => 9]),

            'delete: three terms, one of them IS NULL' => static fn(): Delete
                => (new Delete('user_api_token'))
                    ->where(['user_id' => 1])
                    ->where(new IsNull('revoked_on'))
                    ->where(new Operator('expires_on', Operator::LT, '2026-09-22')),
        ];
    }
}
