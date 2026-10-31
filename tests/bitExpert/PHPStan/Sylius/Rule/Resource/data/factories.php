<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Book;

final class BookFactory
{
    public function createNew(): Book
    {
        return new Book();
    }

    public function createForAuthor(string $authorId): Book
    {
        return $this->createNew();
    }
}

final class MethodNotExistsFactory
{
    public function createNew(): Book
    {
        return new Book();
    }
}

final class ClassOnlyFactory
{
    // No createNew(): the ResourceBundle default method is missing.
}

final class CallableFactory
{
    public function createNew(): Book
    {
        return new Book();
    }

    public static function create(): Book
    {
        return new Book();
    }
}

final class CallableFactoryNotStatic
{
    public function create(): Book
    {
        return new Book();
    }
}

final class CallableFactoryWrongMethod
{
    public static function createNew(): Book
    {
        return new Book();
    }
}
