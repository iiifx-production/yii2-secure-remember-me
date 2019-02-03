<?php

namespace iiifx\yii2\SecureRememberMe\components;

use iiifx\LazyInit\LazyInitTrait;
use iiifx\yii2\SecureRememberMe\models\RememberMe;
use Yii;
use yii\base\BaseObject;
use yii\base\BootstrapInterface;
use yii\db\ActiveRecord;
use yii\web\Cookie;
use yii\web\IdentityInterface;

/**
 * Class Manager
 */
class Manager extends BaseObject implements BootstrapInterface
{
    use LazyInitTrait;

    /**
     * Ключ для размещения в куки
     *
     * @var string
     */
    public $cookieKey = 'remember-me';

    /**
     * Вреья жизни данных в куки и БД
     *
     * @var int
     */
    public $lifetime = 2592000; # 30 дней

    /**
     * Класс сущности пользователя
     *
     * @var IdentityInterface|ActiveRecord
     */
    public $userClass = 'common\models\User';

    /**
     * {@inheritdoc}
     *
     * @param $app
     *
     * @return bool
     * @throws \ErrorException
     * @throws \Throwable
     * @throws \yii\base\Exception
     */
    public function bootstrap($app)
    {
        # Только для незалогиненных
        if (Yii::$app->getUser()->isGuest) {
            # Если имеются данные в куки
            if ($this->hasCookieString()) {
                # И если они правильные
                if ($this->isValid()) {
                    # Получаем пользователя
                    $userId = $this->getUserId();
                    $userClass = $this->userClass;
                    /** @var IdentityInterface $user */
                    if (($user = $userClass::findOne($userId))) {
                        # Регенерируем токен
                        $this->regenerate();
                        # Залогинимаем его
                        return Yii::$app->getUser()->login($user);
                    }
                }
                # Иначе удаляем данные
                $this->delete();
            }
        }
        return TRUE;
    }

    /**
     * Создать новый RememberMe в БД и в куки для указанного пользователя
     *
     * @param int $userId
     *
     * @throws \yii\base\Exception
     */
    public function create($userId)
    {
        $token = $this->createUniqueHash();
        $entity = new RememberMe([
            'token_hash' => Yii::$app->getSecurity()->generatePasswordHash($token),
            'user_id' => $userId,
            'date_expires' => date('Y-m-d H:i:s', time() + $this->lifetime),
        ]);
        do {
            $entity->selector = $this->createUniqueHash();
        } while (!$entity->save());
        $this->setCookieData($entity->selector, $token);
    }

    /**
     * Регенерировать RememberMe, продлить время жизни
     *
     * @throws \ErrorException
     * @throws \yii\base\Exception
     */
    public function regenerate()
    {
        if (($entity = $this->getEntity())) {
            $newToken = $this->createUniqueHash();
            $entity->token_hash = Yii::$app->getSecurity()->generatePasswordHash($newToken);
            $entity->date_expires = date('Y-m-d H:i:s', time() + $this->lifetime);
            $entity->save();
            $this->setCookieData($entity->selector, $newToken);
        }
    }

    /**
     * Полностью удалить данные с БД и куки
     *
     * @throws \Exception
     *
     * @throws \Throwable
     */
    public function delete()
    {
        if (($entity = $this->getEntity())) {
            $entity->delete();
        }
        $this->deleteCookieString();
    }

    /**
     * Определить наличие данных RememberMe в куки
     *
     * @return bool
     */
    public function hasCookieString()
    {
        return (bool)$this->getCookieString();
    }

    /**
     * Сверить данные RememberMe в куки и данные с БД на соответствие
     *
     * @return bool
     *
     * @throws \ErrorException
     */
    public function isValid()
    {
        if ($this->hasCookieString() && $this->isCookieDataCorrect()) {
            if (($entity = $this->getEntity())) {
                # Проверяем хэш
                if (Yii::$app->getSecurity()->validatePassword($this->getCookieToken(), $entity->token_hash)) {
                    # Проверяем время жизни
                    if (strtotime($entity->date_expires) > time()) {
                        # Все отлично
                        return TRUE;
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Получить ID пользователя с которым связан токен
     *
     * @return bool|string
     *
     * @throws \ErrorException
     */
    public function getUserId()
    {
        if (($entity = $this->getEntity())) {
            return $entity->user_id;
        }
        return FALSE;
    }

    /**
     * Сгенерировать уникальную хэш-строку
     *
     * @return string
     *
     * @throws \yii\base\Exception
     */
    protected function createUniqueHash()
    {
        return md5(Yii::$app->getSecurity()->generateRandomString(32));
    }

    /**
     * Прочитать данные RememberMe из куки
     *
     * @return bool|string
     */
    protected function getCookieString()
    {
        if (($cookie = Yii::$app->getRequest()->getCookies()->get($this->cookieKey))) {
            return $cookie->value;
        }
        return FALSE;
    }

    /**
     * Сохранить данные RememberMe в куки
     *
     * @param string $selector
     * @param string $token
     */
    protected function setCookieData($selector, $token)
    {
        Yii::$app->getResponse()->getCookies()->add(new Cookie([
            'name' => $this->cookieKey,
            'value' => "{$selector}:{$token}",
            'expire' => time() + $this->lifetime,
        ]));
    }

    /**
     * Удалить данные RememberMe из куки
     */
    protected function deleteCookieString()
    {
        Yii::$app->getResponse()->getCookies()->remove(new Cookie([
            'name' => $this->cookieKey,
        ]));
    }

    /**
     * Получить данные RememberMe с куки
     *
     * @return string[]
     *
     * @throws \ErrorException
     */
    protected function getCookieStringParts()
    {
        return $this->lazyInit(function () {
            if ($this->hasCookieString()) {
                $cookieString = $this->getCookieString();
                $cookieParts = explode(':', $cookieString);
                if (count($cookieParts) === 2) {
                    list($selector, $token) = $cookieParts;
                    return [
                        'selector' => $selector,
                        'token' => $token,
                    ];
                }
            }
            return [];
        }, __METHOD__);
    }

    /**
     * Прочитать селектор с куки
     *
     * @return string
     *
     * @throws \ErrorException
     */
    protected function getCookieSelector()
    {
        return $this->lazyInit(function () {
            $tokenData = $this->getCookieStringParts();
            if (isset($tokenData['selector'])) {
                return $tokenData['selector'];
            }
            return NULL;
        }, __METHOD__);
    }

    /**
     * Прочитать токена с куки
     *
     * @return string
     *
     * @throws \ErrorException
     */
    protected function getCookieToken()
    {
        return $this->lazyInit(function () {
            $tokenData = $this->getCookieStringParts();
            if (isset($tokenData['token'])) {
                return $tokenData['token'];
            }
            return NULL;
        }, __METHOD__);
    }

    /**
     * Убидиться, что данные в куки корректные
     *
     * @return bool
     *
     * @throws \ErrorException
     */
    protected function isCookieDataCorrect()
    {
        return (strlen($this->getCookieSelector()) === 32 && strlen($this->getCookieToken()) === 32);
    }

    /**
     * Получить сущность с БД
     *
     * @return RememberMe
     *
     * @throws \ErrorException
     */
    protected function getEntity()
    {
        return $this->lazyInit(function () {
            return RememberMe::findOne([
                'selector' => $this->getCookieSelector(),
            ]);
        }, __METHOD__);
    }
}
