<?php

namespace app\components;
use app\models\BaseModel;
use yii;
use app\models\Content;
use yii\base\BaseObject;

class Form extends BaseObject
{
    /*
     * Возвращаем хэш полей из дерева контента
     * Результат возвращаем через параметры функции! так как обходим не массив единиц контента, а дерево рекурсией
     *
     */
    public static function getFieldsFromContent(&$contentList, &$result)
    {
        /*if (!is_array($contentList)) {
            $_contentList = [$contentList];
            static::getFieldsFromContent($_contentList, $result);
            return;
        }*/
        foreach ($contentList as $item) {
            /*if (!isset($item['type'])) {
                Yii::info('item without type!!!! $contentList=' . var_export($contentList), __METHOD__);
                continue;
            }/**/
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
            if (isset($item['content_keys']) && in_array('ERROR', $item['content_keys'])) {
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
                $_ErrId = 0;
                $error_content = array_map(function($e) use (&$_ErrId) {
                    return [
                        'id' => $_ErrId++, // id нужен для ключа (key) на фронте
                        'content' => $e,
                        'type' => '',
                        'childs' => [],
                    ];
                }, $error_message);

                $contentList[$i]['childs'] = $error_content;
            }
            if ($item['type'] !== 'field') continue;
            if (isset($item['settings']['fieldname']) && isset($errors[$item['settings']['fieldname']]))
                $contentList[$i]['settings']['error'] = $errors[$item['settings']['fieldname']]['text'];
        }
    }

    public static function checkForm(&$session, &$fields, &$lang, &$formData, &$errors)
    {
        //$site = BaseModel::getSiteFromSession($session);
        foreach ($formData as $name => $value) { // пробегаем именно по входящим данным, здесь мы приводим типы, подготавливаем строки
            if (!isset($fields[$name])) // уберем все что не в форме (лишнее)
            {
                // удаление поля перенес ниже для того чтобы не удалить информацию о загруженных файлах (поле с префиксом _ нет в форме и его херит тут)
                //log_message('error', '-->>> form_check: field name:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                //unset($data[$name]);
                continue;
            }

            if (!isset($fields[$name]['settings'])) continue; // нет настроек поля пока пропускаем
            $fSettings = $fields[$name]['settings'];

            $fType = $fSettings['fieldtype'];

            // приведем типы
            if ($fType === 'integer') {
                //log_message('error', '-->>> form_check: integer field name:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                if ($formData[$name] != '') // установлено
                {
                    //log_message('error', '-->>> form_check: value before:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                    $formData[$name] = str_replace(array(' '), array(''), $formData[$name]); // от греха исправим распространенные ошибки
                    settype($formData[$name], 'integer');
                    //log_message('error', '-->>> form_check: value before:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                } else {
                    $formData[$name] = isset($formData[$name]['default']) ? $formData[$name]['default'] : NULL;
                }
            }
            if ($fType === 'float') {
                $formData[$name] = str_replace(array(' ', ','), array('', '.'), $formData[$name]); // от греха исправим распространенные ошибки
                $formData[$name] = preg_replace('/[^0-9\\.\\-\\+]/', '', $formData[$name]); // от греха исправим распространенные ошибки
                if ($formData[$name] != '') // установлено
                {
                    settype($formData[$name], 'float');
                } else {
                    $formData[$name] = isset($formData[$name]['default']) ? $formData[$name]['default'] : NULL;
                }
            }

            if ($fType === 'list' || $fType === 'tree') {
                // если поле типа список и значение пустое, то значит в БД нам надо писать null
                if ($formData[$name] === '') $formData[$name] = null;
            }

            $text_fields = [
                'string', 'name', 'name_name', 'name_surname', // с этими понятно
                'date', 'datetime', // тут тоже кроме цифр и - и пробелов ничего быть не должно
                //'phone', 'email', // в этих дополнительно не должно быть ничего такого, хотя емайл содержит символ @
                // в 'phone' чуть выше удалил все не цифры
                // email = a@df.ru"><script>alert(1)</script>
                // email мы проверяем ниже. я уже не помню зачем я разнес проверку по типам на 2 цикла
                // вспомнил в первом цикле мы не проверяем а чистим и приводим к формату
            ];
            /*
            //if ($form[$name]['type']=='string' || $form[$name]['type']=='name')
            if (in_array($form[$name]['type'], $text_fields))
            {
                if ( (!isset($form[$name]['html']) || !$form[$name]['html']) )
                {
                    if ($data[$name]!='') // установлено
                    {
                        $data[$name] = htmlspecialchars($data[$name]);//html_purify($data[$name], 'comment');
                    }
                }
                else
                {
                    if ($data[$name] != '') // установлено
                    {
                        $config = isset($form[$name]['htmlpurifier']) ? $form[$name]['htmlpurifier'] : 'comment';
                        //log_message('error', '-->>> form_check: value before:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                        $data[$name] = html_purify($data[$name], $config);
                        //log_message('error', '-->>> form_check: value after:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                    }
                }

                // проверка языков
                if (isset($form[$name]['trans']) && $form[$name]['trans'] && sizeof($languages)>1)
                {
                    foreach ($languages as $l)
                    {
                        if ($l['is_default']) continue;
                        if (isset($data[$name.'-'.$l['lang']]) && $data[$name.'-'.$l['lang']])
                        {
                            $data[$name.'-'.$l['lang']] = htmlspecialchars($data[$name.'-'.$l['lang']]);
                        }
                    }
                }
            }

            if ($form[$name]['type']=='text')
            {
                if (isset($form[$name]['noteditor']) && $form[$name]['noteditor'] && (!isset($form[$name]['html']) || !$form[$name]['html']))
                {
                    if ($data[$name]!='') // установлено
                    {
                        $data[$name] = htmlspecialchars($data[$name]);
                    }
                }
                else
                {
                    if ($data[$name] != '') // установлено
                    {
                        $config = isset($form[$name]['htmlpurifier']) ? $form[$name]['htmlpurifier'] : 'comment';
                        //log_message('error', '-->>> form_check: value before:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                        $data[$name] = html_purify($data[$name], $config);
                        //log_message('error', '-->>> form_check: value after:'.$name.'='. (isset($data[$name])?$data[$name]:'---'));
                    }
                }

                // проверка языков
                if (isset($form[$name]['trans']) && $form[$name]['trans'] && sizeof($languages)>1)
                {
                    foreach ($languages as $l)
                    {
                        if ($l['is_default']) continue;
                        if (isset($data[$name.'-'.$l['lang']]) && $data[$name.'-'.$l['lang']])
                        {
                            if (isset($form[$name]['noteditor']) && $form[$name]['noteditor'])
                            {
                                $data[$name.'-'.$l['lang']] = htmlspecialchars($data[$name.'-'.$l['lang']]);
                            }
                            else
                            {
                                //log_message('error', '-->>> form_check: value before:'.$name.'-'.$l['lang'].'='. ($data[$name.'-'.$l['lang']]));
                                $data[$name.'-'.$l['lang']] = html_purify($data[$name.'-'.$l['lang']], 'comment');
                                //log_message('error', '-->>> form_check: value after :'.$name.'-'.$l['lang'].'='. ($data[$name.'-'.$l['lang']]));
                            }
                        }
                    }
                }
            }
            /**/
        }

        // проверка обязательных полей, на типы данных и прочее
        foreach ($fields as $name => $field) {
            if (!isset($field['settings'])) continue; // нет настроек поля пока пропускаем
            $fSettings = $field['settings'];

            if (isset($fSettings['not_check']) && $fSettings['not_check']) {
                continue;
            }

            if (isset($fSettings['required']) && $fSettings['required']) {
                //Yii::info('-->>> form_check: field ' . $name . ' is required', __METHOD__);
                if ($fSettings['fieldtype'] == 'tree') {
                    if (!isset($formData[$name]) || !$formData[$name] || $formData[$name] == '') {
                        if (!isset($errors[$name])) $errors[$name] = array('title' => $fSettings['label'], 'text' => '');
                        $errors[$name]['text'] .= 'Поле обязательно для заполнения.'; //"'.$field['label'].'"; lang('common_required');
                    }
                } /*else if (is_array($form[$name]['type']))
                    {
                        if (!isset($data[$name]) || !$data[$name] || !isset($data[$name]['rows']) || !$data[$name]['rows'])
                        {
                            if (!isset($errors[$name])) $errors[$name] = array('title'=>$fSettings['label'], 'text'=>'');
                            $errors[$name]['text'] .= lang('common_required'); //'Поле обязательно для заполнения.'; //"'.$field['label'].'"
                        }
                    }*/
                else {
                    if (!isset($formData[$name]) || !$formData[$name]) {
                        if (!isset($errors[$name])) $errors[$name] = array('title' => $fSettings['label'], 'text' => '');
                        $errors[$name]['text'] .= 'Поле обязательно для заполнения.'; //"'.$field['label'].'" lang('common_required');
                    }
                }
            }

            if ($fSettings['fieldtype']=='tree' && isset($fSettings['selfrefto']) && isset($formData[$fSettings['selfrefto']]) && $formData[$fSettings['selfrefto']] && isset($formData[$name])) // надо проверить чтобы небыло зацикливания веток (например сам себе парент)
            {
                // 1 сам себе парент
                if ($formData[$fSettings['selfrefto']] == $formData[$name]) {
                    if (!isset($errors[$name])) $errors[$name] = array('title' => $fSettings['label'], 'text' => '');
                    $errors[$name]['text'] .= 'Поле не может находиться само в себе'; //lang('common_error_recursion'); //'Поле не может находиться само в себе'; //  "'.$field['title'].'"
                }
                // 2 поле не может находится в своем потомке (сцуко рекурсия)
                // найдем сам элемент
                $item = static::find_node_in_tree($field['childs'], $formData[$fSettings['selfrefto']], $fSettings['selfrefto']);
                // теперь попробуем найти знаечние поля в $item['childs']
                $parent = static::find_node_in_tree($item['childs'], $formData[$name], $fSettings['selfrefto']);
                if ($parent) {
                    if (!isset($errors[$name])) $errors[$name] = array('title' => $fSettings['label'], 'text' => '');
                    $errors[$name]['text'] .= 'Поле не может находиться в своем потомке';//lang('common_error_recursion_deep'); //'Поле не может находиться в своем потомке';// "'.$field['title'].'"
                }
            }

        }

        return !$errors;
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

    public static function find_node_in_tree(&$tree, $itemid, $optionkey, $optionchilds = 'childs')
    {
        if (!is_array($tree)) return false;
        foreach ($tree as $node)
        {
            if ($node[$optionkey] == $itemid)
            {
                return $node;
            }
            if ($node[$optionchilds])
            {
                $result = static::find_node_in_tree($node[$optionchilds], $itemid, $optionkey);
                if ($result)
                    return $result;
            }
        }
        return false;
    }

}