<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Computed extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'computed' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = $this->calculate_value( $object_id, $context_type );
        $disabled = $this->args['disabled'] ?? false ? ' data-disabled="1"' : '';
        echo '<span class="gm2-computed" data-key="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html( $value ) . '</span>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $computed = $this->calculate_value( $object_id, $context_type );
        if ( $computed !== null && $computed !== '' ) {
            $this->update_value( $object_id, $computed, $context_type );
        } else {
            $this->delete_value( $object_id, $context_type );
        }
    }

    protected function calculate_value( $object_id, $context_type ) {
        $formula = $this->args['formula'] ?? null;
        $cb      = $this->args['callback'] ?? null;

        if ( $formula ) {
            $value = $this->evaluate_formula( $formula, $object_id, $context_type );
            if ( $value !== null ) {
                return $value;
            }
        }

        if ( is_callable( $cb ) ) {
            return call_user_func( $cb, $object_id );
        }

        return null;
    }

    protected function evaluate_formula( $formula, $object_id, $context_type ) {
        preg_match_all( '/{([A-Za-z0-9_\-]+)}/', $formula, $matches );
        $replacements = array();

        foreach ( $matches[1] as $key ) {
            $raw_value = $this->get_meta_value( $object_id, $key, $context_type );
            $numeric   = $this->sanitize_numeric_value( $raw_value );

            if ( null === $numeric ) {
                return null;
            }

            $replacements[ '{' . $key . '}' ] = $numeric;
        }

        $expr = strtr( $formula, $replacements );

        if ( preg_match( '/[^0-9+\-*\/().\s]/', $expr ) ) {
            return null;
        }

        $expr   = trim( $expr );
        $result = $this->evaluate_numeric_expression( $expr );

        if ( null === $result ) {
            return null;
        }

        if ( is_nan( $result ) || is_infinite( $result ) ) {
            return null;
        }

        if ( abs( $result - round( $result ) ) < 1e-9 ) {
            return (int) round( $result );
        }

        return $result;
    }

    protected function sanitize_numeric_value( $value ) {
        if ( null === $value || '' === $value ) {
            return '0';
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }

        $value = trim( (string) $value );

        if ( '' === $value ) {
            return '0';
        }

        if ( preg_match( '/^-?(?:\d+\.?\d*|\d*\.\d+)$/', $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            $value = (string) (float) $value;

            if ( preg_match( '/^-?(?:\d+\.?\d*|\d*\.\d+)$/', $value ) ) {
                return $value;
            }
        }

        return null;
    }

    protected function evaluate_numeric_expression( $expression ) {
        if ( '' === $expression ) {
            return null;
        }

        $length   = strlen( $expression );
        $position = 0;

        $result = $this->parse_expression_level( $expression, $position, $length );

        if ( null === $result ) {
            return null;
        }

        $this->skip_whitespace( $expression, $position, $length );

        if ( $position !== $length ) {
            return null;
        }

        return $result;
    }

    private function parse_expression_level( $expression, &$position, $length ) {
        $value = $this->parse_term( $expression, $position, $length );

        if ( null === $value ) {
            return null;
        }

        while ( true ) {
            $this->skip_whitespace( $expression, $position, $length );

            if ( $position >= $length ) {
                break;
            }

            $operator = $expression[ $position ];

            if ( '+' !== $operator && '-' !== $operator ) {
                break;
            }

            $position++;

            $right = $this->parse_term( $expression, $position, $length );

            if ( null === $right ) {
                return null;
            }

            if ( '+' === $operator ) {
                $value += $right;
            } else {
                $value -= $right;
            }
        }

        return $value;
    }

    private function parse_term( $expression, &$position, $length ) {
        $value = $this->parse_factor( $expression, $position, $length );

        if ( null === $value ) {
            return null;
        }

        while ( true ) {
            $this->skip_whitespace( $expression, $position, $length );

            if ( $position >= $length ) {
                break;
            }

            $operator = $expression[ $position ];

            if ( '*' !== $operator && '/' !== $operator ) {
                break;
            }

            $position++;

            $right = $this->parse_factor( $expression, $position, $length );

            if ( null === $right ) {
                return null;
            }

            if ( '/' === $operator ) {
                if ( abs( $right ) < 1e-12 ) {
                    return null;
                }

                $value /= $right;
            } else {
                $value *= $right;
            }
        }

        return $value;
    }

    private function parse_factor( $expression, &$position, $length ) {
        $this->skip_whitespace( $expression, $position, $length );

        if ( $position >= $length ) {
            return null;
        }

        $sign = 1;

        while ( $position < $length ) {
            $char = $expression[ $position ];

            if ( '+' === $char ) {
                $position++;
                $this->skip_whitespace( $expression, $position, $length );
                continue;
            }

            if ( '-' === $char ) {
                $sign *= -1;
                $position++;
                $this->skip_whitespace( $expression, $position, $length );
                continue;
            }

            break;
        }

        if ( $position >= $length ) {
            return null;
        }

        $char = $expression[ $position ];

        if ( '(' === $char ) {
            $position++;
            $value = $this->parse_expression_level( $expression, $position, $length );

            if ( null === $value ) {
                return null;
            }

            $this->skip_whitespace( $expression, $position, $length );

            if ( $position >= $length || ')' !== $expression[ $position ] ) {
                return null;
            }

            $position++;

            return $sign * $value;
        }

        $number = $this->parse_number( $expression, $position, $length );

        if ( null === $number ) {
            return null;
        }

        return $sign * $number;
    }

    private function parse_number( $expression, &$position, $length ) {
        $this->skip_whitespace( $expression, $position, $length );

        $start     = $position;
        $has_digit = false;
        $has_dot   = false;

        while ( $position < $length ) {
            $char = $expression[ $position ];

            if ( ctype_digit( $char ) ) {
                $has_digit = true;
                $position++;
                continue;
            }

            if ( '.' === $char && ! $has_dot ) {
                $has_dot = true;
                $position++;
                continue;
            }

            break;
        }

        if ( ! $has_digit ) {
            return null;
        }

        $number_str = substr( $expression, $start, $position - $start );

        if ( '' === $number_str || '.' === $number_str || '-' === $number_str || '+' === $number_str ) {
            return null;
        }

        return (float) $number_str;
    }

    private function skip_whitespace( $expression, &$position, $length ) {
        while ( $position < $length && ctype_space( $expression[ $position ] ) ) {
            $position++;
        }
    }

    protected function get_meta_value( $object_id, $key, $context_type ) {
        switch ( $context_type ) {
            case 'user':
                return get_user_meta( $object_id, $key, true );
            case 'term':
                return get_term_meta( $object_id, $key, true );
            case 'comment':
                return get_comment_meta( $object_id, $key, true );
            case 'option':
                return get_option( $key );
            case 'site':
                return get_site_option( $key );
            default:
                return get_post_meta( $object_id, $key, true );
        }
    }
}

