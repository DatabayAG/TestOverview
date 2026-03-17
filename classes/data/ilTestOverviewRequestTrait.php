<?php

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;

trait ilTestOverviewRequestTrait
{
    public static string $_GET = 'get';
    public static string $_POST = 'post';
    public static string $TYPE_INT = 'int';
    public static string $TYPE_STRING = 'string';
    public static string $TYPE_LIST_INT = 'list_int';
    public static string $TYPE_LIST_STRING = 'list_string';

    private function getWrapperByRequestType($request_type): ArrayBasedRequestWrapper
    {
        global $DIC;
        $wrapper = $DIC->http()->wrapper();

        if ($request_type == self::$_POST) {
            return $wrapper->post();
        }
        return $wrapper->query();
    }

    private function retrieveIntOrZeroFrom(string $request_type, string $param): int
    {
        global $DIC;
        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        $value = 0;
        if ($wrapper->has($param)) {
            $value = $wrapper->retrieve(
                $param,
                $refinery->byTrying([
                    $refinery->kindlyTo()->int(),
                    $refinery->custom()->transformation(static function ($value): int {
                        if ($value === '') {
                            return 0;
                        }

                        return $value;
                    })
                ])
            );
        }
        return $value;
    }

    private function retrieveStringFrom(string $request_type, string $param): string
    {
        global $DIC;
        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        $value = '';
        if ($wrapper->has($param)) {
            $value = $wrapper->retrieve(
                $param,
                $refinery->byTrying([
                    $refinery->kindlyTo()->string(),
                    $refinery->custom()->transformation(static function ($value): string {
                        return $value;
                    })
                ])
            );
        }
        return $value;
    }

    private function retrieveListOfStringFrom(string $request_type, string $param): array
    {
        global $DIC;
        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        $value = [];
        if ($wrapper->has($param)) {
            $value = $wrapper->retrieve(
                $param,
                $refinery->byTrying([
                    $refinery->kindlyTo()->dictOf($refinery->kindlyTo()->string()),
                    $refinery->always([])
                ])
            );
        }
        return $value;
    }

    private function retrieveListOfIntFrom(string $request_type, string $param): array
    {
        global $DIC;
        $refinery = $DIC->refinery();
        $wrapper = $this->getWrapperByRequestType($request_type);

        $value = [];
        if ($wrapper->has($param)) {
            $value = $wrapper->retrieve(
                $param,
                $refinery->byTrying([
                    $refinery->kindlyTo()->dictOf($refinery->kindlyTo()->int())
                ])
            );
        }

        return $value;
    }

    private function retrieveFromRequest(string $param, string $value_type)
    {
        global $DIC;
        $base_wrapper = $DIC->http()->wrapper();

        $request_type = self::$_GET;
        if ($base_wrapper->query()->has($param)) {
            $request_type = self::$_GET;
        } elseif ($base_wrapper->post()->has($param)) {
            $request_type = self::$_POST;
        }

        if ($value_type == self::$TYPE_INT) {
            return $this->retrieveIntOrZeroFrom($request_type, $param);
        }
        if ($value_type == self::$TYPE_STRING) {
            return $this->retrieveStringFrom($request_type, $param);
        }
        if ($value_type == self::$TYPE_LIST_INT) {
            return $this->retrieveListOfIntFrom($request_type, $param);
        }
        if ($value_type == self::$TYPE_LIST_STRING) {
            return $this->retrieveListOfStringFrom($request_type, $param);
        }
    }

    private function hasValue(string $request_type, string $param): bool
    {
        $wrapper = $this->getWrapperByRequestType($request_type);
        return $wrapper->has($param);
    }
}
