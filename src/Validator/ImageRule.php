<?php
namespace Exceedone\Exment\Validator;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Concerns\ValidatesAttributes;

/**
 * ImageRule.
 */
class ImageRule implements Rule
{
    use ValidatesAttributes;

    /**
    * Check Validation
    *
    * @param  string  $attribute
    * @param  mixed  $value
    * @return bool
    */
    public function passes($attribute, $value)
    {
        // not check null or empty. Check by other required rule.
        if (is_nullorempty($value)) {
            return true;
        }

        if (is_string($value)) {
            $ext = pathinfo($value, PATHINFO_EXTENSION);
            return in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'svg']);
        }

        return $this->validateMimes($attribute, $value, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg']);
    }

    /**
     * get validation error message
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.image');
    }
}
