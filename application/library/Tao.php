<?php

/*
 * TODO: Make implementation more robust
 */
namespace Moca\Tao
{

class Object
{
    public $id;
    public $type;
    public $timestamp;
    public $updateTime;
    public $version;
    public $properties;

    public function __construct($id, $type, $properties)
    {
        $this->id = intval($id);
        $this->type = $type;
        $this->properties = $properties;
    }
}

class Association
{
    public $timestamp;
    public $updateTime;
    public $version;
    public $from;
    public $type;
    public $to;
    public $properties;

    public function __construct($from, $type, $to, $properties)
    {
        $this->from = intval($from);
        $this->type = $type;
        $this->to = intval($to);
        if (\Predicates::isNull($properties)) {
            $this->properties = [];
        } else {
            $this->properties = $properties;
        }
    }
}

}

namespace {
class Tao
{
    private static function convertToArray($properties) {
        return array_reduce($properties, function($carry, $item) { $carry[] = $item; return $carry; }, []);
    }

    private static function convertToJson($properties) {
        if (Predicates::isEmpty($properties)) {
            return null;
        } else {
            return json_encode(self::convertToMap($properties), JSON_UNESCAPED_UNICODE);
        }
    }

    private static function convertToMap($array)
    {
        $result = [];
        for ($index = 0, $count = count($array); $index < $count; $index += 2) {
            $result[$array[$index]] = strval($array[$index + 1]);
        }
        return $result;
    }

    private static function loadProperties($target, $response)
    {
        for ($index = 0, $count = count($response); $index < $count; $index += 2) {
            $key = $response[$index];
            $value = $response[$index + 1];
            switch ($key) {
            case "\$I\$":
                $target->id = intval($value);
                break;
            case "\$TS\$":
                $target->timestamp = intval($value);
                break;
            case "\$UT\$":
                $target->updateTime = intval($value);
                break;
            case "\$V\$":
                $target->version = intval($value);
                break;
            case "\$T\$":
                $target->type = $value;
                break;
            case "\$F\$":
                $target->from = $value;
                break;
            case "\$TO\$":
                $target->to = $value;
                break;
            case "\$P\$":
                $target->properties = json_decode($value);
                break;
            default:
                throw new Exception("Incompatible Protocol: '$key':'$value'");
                break;
            }
        }
        return $target;
    }

    private static function loadObjectStrict($response, $type = NULL, $properties = NULL)
    {
        return self::loadObject(Preconditions::checkNotEmpty($response), $type, $properties);
    }

    private static function loadObject($response, $type = NULL, $properties = NULL)
    {
        return self::loadProperties(new Moca\Tao\Object(0, $type, $properties), $response);
    }

    private static function loadObjectList($response, $type = NULL, $properties = NULL)
    {
        $response = Preconditions::checkNotEmpty($response);
        $list = [];
        $start = $index = 0;
        for ($count = count($response); $index < $count;) {
            if ($response[$index] === "\$\$\$SEP\$\$\$") {
                $list[] = self::loadObject(array_slice($response, $start, $index - $start));
                ++$index;
                $start = $index;
            } else {
                $index += 2;
            }
        }
        $list[] = self::loadObject(array_slice($response, $start, $index - $start));
        return $list;
    }

    private static function loadAssociation($response, $from = 0, $type = NULL, $to = 0, $properties = NULL)
    {
        return self::loadProperties(new Moca\Tao\Association($from, $type, $to, $properties), $response);
    }

    private static function loadAssociationStrict($response, $from = 0, $type = NULL, $to = 0, $properties = NULL)
    {
        return self::loadAssociation(Preconditions::checkNotEmpty($response), $from, $type, $to, $properties);
    }

    private static function loadAssociationList($response, $from, $type)
    {
        if (Predicates::isEmpty($response)) {
            return $response;
        }
        $list = [];
        $start = $index = 0;
        for ($count = count($response); $index < $count;) {
            if ($response[$index] === "\$\$\$SEP\$\$\$") {
                $list[] = self::loadAssociation(array_slice($response, $start, $index - $start), $from, $type, NULL, NULL);
                ++$index;
                $start = $index;
            } else {
                $index += 2;
            }
        }
        $list[] = self::loadAssociation(array_slice($response, $start, $index - $start), $from, $type, NULL, NULL);
        return $list;
    }

    private static function validateType($type, $candidates, $strict)
    {
        $type = strtoupper(Preconditions::checkNotEmpty($type));
        if (!in_array($type, $candidates)) {
            if ($strict) {
                throw new Exception("Invalid tao type " . $type);
            } else {
                return NULL;
            }
        }
        return $type;
    }

    private static function validateObjectType($type)
    {
        return self::validateType($type, ["USER", "EVENT", "MOMENT", "COMMENT", "PHOTO", "CIRCLE"], true);
    }

    private static function validateAssociationType($type, $strict = true)
    {
        return self::validateType($type, ["AUTHORED_BY", "AUTHORED", "COMMENT", "LIKED_BY", "LIKES", "ATTACHMENT", "ATTEND", "OWNS", "OWNED_BY", "MEMBER", "MEMBER_OF"], $strict);
    }

    private static function flattenArray($array)
    {
        return array_reduce($array, function($carry, $arg) {
            if (is_array($arg)) {
                $carry = array_merge($carry, self::flattenArray($arg));
            } else if (Predicates::isNotNull($arg)) {
                $carry[] = $arg;
            }
            return $carry;
        }, []);
    }

    private static function callTao($command, ...$args)
    {
        $finalArgs = self::flattenArray([$command, $args]);
        $result = call_user_func_array([Yii::$app->tao, "rawCommand"], $finalArgs);
        if ($result === false) {
            throw new Exception("Failed to call TAO command " . json_encode($finalArgs, JSON_UNESCAPED_UNICODE) . " " . Yii::$app->tao->getLastError());
        }
        //error_log('calling ' . var_export($finalArgs, true) . ' yields: ' . var_export($result, true));
        return $result;
    }

    private static function validateCreateTime($createTime, $strict = false)
    {
        if (strval(intval($createTime)) !== strval($createTime)) {
            if ($strict) {
                throw new Exception("Invalid create time '$createTime'");
            } else {
                return NULL;
            }
        }
        return $createTime;
    }

    private static function validateProperties($properties)
    {
        if (count($properties) % 2 == 1) {
            throw new Exception("key value items are not paired");
        }
        return $properties;
    }

    public static function addObject($type, ...$properties)
    {
        if (count($properties) % 2 == 1) {
            self::validateCreateTime($properties[0]);
        } else {
            array_unshift($properties, -1);
        }
        $realProperties = array_splice($properties, 1);
        return self::loadObjectStrict(self::callTao("tao_obj_add_json", self::validateObjectType($type), $properties[0], self::convertToJson($realProperties)), $type, self::convertToMap($realProperties));
    }

    public static function addObjectArray($type, $properties)
    {
        return forward_static_call_array(["Tao", "addObject"], array_merge([$type], self::convertToArray($properties)));
    }

    public static function getObject(...$id)
    {
        $count = Preconditions::checkPositive(count($id));
        $result = self::callTao("tao_obj_get", 0, $id);
        return $count > 1 ? self::loadObjectList($result) : self::loadObjectStrict($result);
    }

    public static function getObjectArray($ids)
    {
        return forward_static_call_array(["Tao", "getObject"], $ids);
    }

    public static function deleteObject($id)
    {
        if (intval(self::callTao("tao_obj_delete", $id)) != 1) {
            throw new Exception("Could not delete object " . $id . " from tao");
        }
        return true;
    }

    public static function updateObject($id, ...$properties)
    {
        $properties = self::validateProperties($properties);
        $result = self::loadObjectStrict(self::callTao("tao_obj_update_json", $id, self::convertToJson($properties)), null, self::convertToMap($properties));
        $result->id = $id;
        $result->timestamp = null;
        return $result;
    }

    public static function updateObjectArray($id, $properties)
    {
        return forward_static_call_array(["Tao", "updateObject"], array_merge([$id], self::convertToArray($properties)));
    }

    public static function addAssociation($from, $type, $to, ...$properties)
    {
        $inverseType = self::validateAssociationType($to, false);
        $flags = Predicates::isNotNull($inverseType) ? 1 : 0;
        if (count($properties) % 2 == 0) {
            /* detect if both inverse association and create time are specified */
            if (Predicates::isNotNull($inverseType)) {
                /* inverse association type is set, the next argument must be create time */
                $realProperties = array_splice($properties, 2);
                $to = $properties[0];
                $properties = [$inverseType, $to, self::validateCreateTime($properties[1]), self::convertToJson($realProperties)];
            } else {
                $realProperties = $properties;
                $properties = [$to, -1, self::convertToJson($realProperties)];
            }
        } else {
            /* detect if either inverse association or create time is specified */
            if (Predicates::isNotNull($inverseType)) {
                $realProperties = array_splice($properties, 1);
                $to = $properties[0];
                $properties = [$inverseType, $to, -1, self::convertToJson($realProperties)];
            } else {
                $realProperties = array_splice($properties, 1);
                $properties = [$to, self::validateCreateTime($properties[0]), self::convertToJson($realProperties)];
            }
        }
        return self::loadAssociation(self::callTao("tao_assoc_add_json", $flags, $from, self::validateAssociationType($type), $properties), $from, $type, $to, self::convertToMap($realProperties));
    }

    public static function addAssociationArray($from, $type, $to, $properties)
    {
        return forward_static_call_array(["Tao", "addAssociation"], array_merge([$from, $type, $to], self::convertToArray($properties)));
    }

    public static function associationExists($from, $type, $to)
    {
        return Predicates::isEmpty(self::callTao("tao_assoc_get", 0, $from, self::validateAssociationType($type), $to)) ? false : true;
    }

    public static function getAssociation($from, $type, ...$to)
    {
        $count = Preconditions::checkPositive(count($to));
        $result = self::callTao("tao_assoc_get", 0, $from, self::validateAssociationType($type), $to);
        if (!empty($result)) {
            
            return $count > 1 ? self::loadAssociationList($result, $from, $type) : self::loadAssociationStrict($result, $from, $type);
        }
        return [];
    }

    public static function getAssociationArray($from, $type, $to)
    {
        return forward_static_call_array(["Tao", "getAssociation"], array_merge([$from, $type], $to));
    }

    public static function deleteAssociation($from, $type, $to, ...$extra)
    {
        $extraCount = count($extra);
        if ($extraCount > 2) {
            throw new Exception("Invalid number of arguments");
        }
        if ($extraCount == 1) {
            $type = [$type, self::validateAssociationType($to)];
            $to = $extra[0];
        } else {
            $type = self::validateAssociationType($type);
        }
        if (intval(self::callTao("tao_assoc_delete", $from, $type, $to)) != 1 + $extraCount) {
            if (is_array($type)) {
                $type = $type[0];
            }
            throw new Exception("Could not delete association ($from) -[:$type]-> ($to) from tao");
        }
        return true;
    }

    public static function countAssociation($from, $type)
    {
        return intval(self::callTao("tao_assoc_count", $from, self::validateAssociationType($type)));
    }

    public static function getAssociationRange($from, $type, $offset, $limit, ...$filter)
    {
        return self::loadAssociationList(self::callTao("tao_assoc_range", 0, $from, self::validateAssociationType($type), $offset, $limit, $filter), $from, $type);
    }

    public static function updateAssociation($from, $type, $to, ...$properties)
    {
        if (count($properties) % 2 == 1) {
            $invertType = self::validateAssociationType($to);
            $to = $properties[0];
            $realProperties = array_slice($properties, 1);
            $properties = [$invertType, $to, self::convertToJson($realProperties)];
            $flags = 1;
        } else {
            $realProperties = $properties;
            $properties = [$to, self::convertToJson($realProperties)];
            $flags = 0;
        }
        $result = self::loadAssociation(self::callTao("tao_assoc_update_json", $flags, $from, self::validateAssociationType($type), $properties), $from, $type, $to, self::convertToMap($realProperties));
        $result->timestamp = null;
        $result->type = null;
        return $result;
    }

    public static function updateAssociationArray($from, $type, $to, $properties)
    {
        return forward_static_call_array(["Tao", "updateAssociation"], array_merge([$from, $type, $to], self::convertToArray($properties)));
    }
}
}

?>
