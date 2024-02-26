<?php

namespace App\Http\Controllers;

use App\Models\Books;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * @throws \Exception
     */
    public function validateBook($book_id){
        $book = DB::table('books')
            ->join('book_authors', 'books.id', '=', 'book_authors.book_id')
            ->join('authors', 'book_authors.author_id', '=', 'authors.id')
            ->join('book_genres', 'books.id', '=', 'book_genres.book_id')
            ->join('genres', 'book_genres.genre_id', '=', 'genres.id')
            ->select('books.id', 'books.name', 'books.created_at')
            ->selectRaw("string_agg(DISTINCT authors.name, ', ') as authors")
            ->selectRaw("string_agg(DISTINCT genres.genre, ', ') as genres")
            ->where('books.id', $book_id)
            ->groupBy('books.id', 'books.name', 'books.created_at')
            ->first();

        if (!$book) {
            throw new \Exception('Book not found', 404);
        }

        return $book;
    }

}
