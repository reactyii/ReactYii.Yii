<?php

namespace app\components;
use yii;
use app\models\Content;
use yii\base\BaseObject;

class Form extends BaseObject
{
    /*
     * Возвращаем хэш полей из дерева контента
     */
    public static function getFieldsFromContent(&$contentList, &$result)
    {
        /*if (!is_array($contentList)) {
            $_contentList = [$contentList];
            static::getFieldsFromContent($_contentList, $result);
            return;
        }*/
        foreach ($contentList as $item) {
            if ($item['type'] == 'field') $result[$item['settings']['fieldname']] = $item;
            if (isset($item['childs']) && $item['childs']) {
                static::getFieldsFromContent($item['childs'], $result);
            }
        }
    }

    public static function fillForm(&$contentList, &$formData)
    {
        foreach ($contentList as $i => $item) {
            if (isset($item['childs']) && $item['childs']) {
                static::fillForm($contentList[$i]['childs'], $formData);
            }
            if ($item['type'] !== 'field') continue;
            $contentList[$i]['settings']['value'] = isset($formData[$item['settings']['fieldname']]) ? $formData[$item['settings']['fieldname']] : '';
            //Yii::info($item['settings']['fieldname'] . '=' . $contentList[$i]['settings']['value'], __METHOD__);
        }
    }

    public static function setError(&$contentList, &$errors)
    {
        if (!$errors) return;

        foreach ($contentList as $i => $item) {
            if (isset($item['childs']) && $item['childs']) {
                static::setError($contentList[$i]['childs'], $errors);
            }
            if (in_array('ERROR', $item['content_keys'])) {
                $error_fields = [];
                $error_message = [];
                foreach ($errors as $f=>$err) {
                    if ($f == '') continue;
                    $error_fields[] = '&quot;' . $err['title'] . '&quot;';
                }
                if ($error_fields) {
                    if (count($error_fields) == 1) {
                        $error_message[] = 'Поле ' . implode(', ', $error_fields) . ' заполнено неправильно.';
                        //$error_message[] = str_replace('%field%', implode(', ', $error_fields), lang('common_field_wrong'));
                    } else {
                        $error_message[] = 'Поля ' . implode(', ', $error_fields) . ' заполнены неправильно.';
                        //$error_message[] = str_replace('%fields%', implode(', ', $error_fields), lang('common_fields_wrong'));
                    }
                }
                if (isset($errors['']))
                {
                    //if ($error_message) $error_message .= '<br />';
                    $error_message[] = $errors['']['text'];
                }
                $error_content = array_map(function($e) {
                    return [
                        'id' => -9999, // id нужен для ключа (key) на фронте
                        'content' => $e,
                        'type' => '',
                        'childs' => [],
                    ];
                }, $error_message);

                $contentList[$i]['childs'] = $error_content;
            }
            if ($item['type'] !== 'field') continue;
            if (isset($errors[$i]))
                $contentList[$i]['settings']['error'] = $errors[$i]['text'];
        }
    }

    public static function checkForm(&$fields, &$lang, &$formData, &$errors)
    {

        return true;
    }

    public static function getFormDataFromContentArgsPath(&$content_args, $ignore_empty = true)
    {
        $res = [];
        if (sizeof($content_args) > 0) {
            $argsstr = array_shift($content_args);

            // очень большая проблема передать одиноч символ '%' в пути (в get проблем нет). Решение: на фронте делаем след
            // 1. arg = encodeURIComponent(i) + '=' + encodeURIComponent(g[i] as string)
            // 2. arg = arg.replace('~', '~7E').replace('%', '~25')
            $argsstr = str_replace(['~7E', '~7e'], ['~', '~'], str_replace(['~25'], ['%'], $argsstr));
            // urldecode не нужен так как фрэймворк сам это делает замены на фронте мы делаем до encodeURIComponent
            //$argsstr = urldecode(str_replace(['~7E', '~7e'], ['~', '~'], str_replace(['~25'], ['%'], $argsstr)));

            $tmp = explode('&', $argsstr);
            foreach ($tmp as $pair) {
                $_tmp = explode('=', $pair);
                //$res[$_tmp[0]] = $_tmp[1];
                if (preg_match('/^[a-z0-9_\\[\\]]+$/i', $_tmp[0])) // на всякий пожарный страханемся. здесь имя может быть массив
                {
                    if (preg_match('/\\[\\]$/i', $_tmp[0])) {
                        $fname = rtrim($_tmp[0], '\\[\\]'); // убираем из имени поля []
                        if (!isset($res[$fname])) $res[$fname] = [];

                        // у массива всегда $ignore_empty = true
                        if (sizeof($_tmp) > 1) $res[$fname][] = $_tmp[1];
                    } else {
                        if (sizeof($_tmp) == 1) {
                            if (!$ignore_empty) $res[$_tmp[0]] = ''; // хз какое значение тут поставить по идее это всегда равно пустой строке
                        } else {
                            $res[$_tmp[0]] = $_tmp[1];
                        }
                    }
                }
            }
        }

        return $res;
    }

}