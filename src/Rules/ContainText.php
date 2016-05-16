<?php
/******************************************************************************
 * An implementation of dicto (scg.unibe.ch/dicto) in and for PHP.
 *
 * Copyright (c) 2016 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received
 * a copy of the licence along with the code.
 */

namespace Lechimp\Dicto\Rules;

use Lechimp\Dicto\Definition as Def;
use Lechimp\Dicto\Analysis\Query;
use Lechimp\Dicto\Analysis\Violation;

/**
 * This checks wheather there is some text in the definition of an entity.
 */
class ContainText extends Property {
    /**
     * @inheritdoc
     */
    public function name() {
        return "contain_text";
    } 

    /**
     * @inheritdoc
     */
    public function check_arguments(array $arguments) {
        if (count($arguments) != 1) {
            throw new \InvalidArgumentException(
                "One argument is required when using a contain text.");
        }
        $regexp = $arguments[0];
        if (!is_string($regexp) || @preg_match("%$regexp%", "") === false) {
            throw new \InvalidArgumentException(
                "Invalid regexp '$regexp' when using contain text.");
        }
    }

    /**
     * @inheritdoc
     */
    public function compile(Query $query, Rule $rule) {
        $builder = $query->builder();
        $mode = $rule->mode();
        $checked_on = $rule->checked_on();
        $regexp = $rule->argument(0);
        if ($mode == Rule::MODE_CANNOT || $mode == Rule::MODE_ONLY_CAN) {
            return $builder
                ->select
                    ( "id as entity_id"
                    , "file"
                    , "source"
                    )
                ->from($query->entity_table())
                ->where
                    ( $query->compile_var($query->entity_table(), $checked_on)
                    , "source REGEXP ?"
                    )
                ->setParameter(0, $regexp)
                ->execute();
        }
        if ($mode == Rule::MODE_MUST) {
            return $builder
                ->select
                    ( "id as entity_id"
                    , "file"
                    , "source"
                    )
                ->from($query->entity_table())
                ->where
                    ( $query->compile_var($query->entity_table(), $checked_on)
                    , "source NOT REGEXP ?"
                    )
                ->setParameter(0, $regexp)
                ->execute();
        }
        throw new \LogicException("Unknown rule mode: '$mode'");
    }

    /**
     * @inheritdoc
     */
    public function to_violation(Rule $rule, array $row) {
        $line_no = 0;
        $line = null;
        $lines = explode("\n", $row["source"]);
        $pattern = $rule->argument(0);
        foreach ($lines as $l) {
            $line_no++;
            if (preg_match("%$pattern%", $l) > 0) {
                $line = $l;
                break;
            }
        }
        if ($line === null) {
            throw new \LogicException(
                "Found '$pattern' with SQL query but not in postprocessing...");
        }
        return new Violation
            ( $rule
            , $row["file"]
            , $line_no
            , $line
            );
    }

    /**
     * @inheritdoc
     */
    public function pprint(Rule $rule) {
        return $this->printable_name().' "'.$rule->argument(0).'"';
    }
}