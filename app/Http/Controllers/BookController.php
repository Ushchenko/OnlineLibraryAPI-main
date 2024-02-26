<?php

namespace App\Http\Controllers;

use App\Models\Authors;
use App\Models\Book_Authors;
use App\Models\Book_Genres;
use App\Models\Books;
use App\Models\Genres;
use App\Models\User_Favorites;
use Illuminate\Http\Request;
use Illuminate\Database;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * @throws \Exception
     */
    public function getAll()
    {
        if(!Books::exists())
            return response()->json('No books found',404);

        return $this->getGenresAuthors();
    }

    /**
     * @throws \Exception
     */
    public function getOne($id)
    {
        $book = $this->validateBook($id);

        return response()->json($book);
    }

    /**
     * @throws \Exception
     */
    public function add(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'genres' => 'required|string',
            'authors' => 'required|string'
        ]);

        # Handle genres
        $genres = explode(',', $request->input('genres'));
        foreach ($genres as $genre) {
            $genre = trim($genre); // Remove any whitespace

            // Convert genre to lowercase and make the first letter uppercase
            $genre = ucfirst(strtolower($genre));

            // Check if the genre name is valid
            if (!preg_match('/^[a-zA-Z\s]+$/', $genre)) {
                return response()->json(['error' => 'Invalid genre name: ' . $genre], 400);
            }

            // Check if the genre exists in the Genres table
            $genreModel = Genres::where('genre', $genre)->first();

            // If the genre doesn't exist, create a new one
            if (!$genreModel) {
                $genreModel = Genres::create(['genre' => $genre]);
            } else {
                $genreModel = Genres::where('genre', $genre)->first(); // Обновляем $genreModel, если жанр уже существует
            };
        }

        /**
         * @throws \Exception
         */
        # Handle authors
        $authors = explode(',', $request->input('authors'));
        foreach ($authors as $author) {
            $author = trim($author); // Remove any whitespace

            // Check if the genre name is valid
            if (!preg_match('/^[a-zA-Z\s\.]+$/', $author)) {
                return response()->json(['error' => 'Invalid author name: ' . $author], 400);
            }

            // Check if the genre exists in the Genres table
            $authorModel = Authors::where('name', $author)->first();

            // If the genre doesn't exist, create a new one
            if (!$authorModel) {
                $authorModel = Authors::create(['name' => $author]);
            };
        }

        # Create book
        $book = Books::create(['name' => $request->input('name')]);

        # Add book to Book_Genres table
        foreach ($genres as $genre){
            $genre = trim($genre); // Remove any whitespace
            // Check if the genre exists in the Genres table
            $genreModel = Genres::where('genre', $genre)->first();

            Book_Genres::create(['book_id' => $book->id, 'genre_id' => $genreModel->id]);
        }

        # Add book to Book_Authors table
        foreach ($authors as $author){
            $author = trim($author); // Remove any whitespace
            // Check if the genre exists in the Genres table
            $authorModel = Authors::where('name', $author)->first();

            Book_Authors::create(['book_id' => $book->id, 'author_id' => $authorModel->id]);
        }

        return response()->json(['book' => $book], 201);
    }

    /**
     * @throws \Exception
     */
    public function remove($id)
    {
        // Найдем книгу по идентификатору с помощью Eloquent
        $book = Books::find($id);

        if (!$book) {
            return response()->json('Book not found', 404);
        }

        // Удаляем связанные записи
        User_Favorites::where('book_id', $id)->delete();
        Book_Authors::where('book_id', $id)->delete();
        Book_Genres::where('book_id', $id)->delete();

        // Удаляем саму книгу
        $book->delete();

        return response()->json('Book deleted successfully', 201);
    }

    /**
     * @throws \Exception
     */
    public function csvExport()
    {
        $books = $this->getGenresAuthors();

        $csvFileName = 'exported_books.csv';
        $filePath = storage_path($csvFileName);
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $handle = fopen($filePath, 'w');
        fputcsv($handle, ['id', 'name', 'created_at', 'authors', 'genres']);

        foreach ($books as $book) {
            fputcsv($handle, [
                $book->id,
                $book->name,
                $book->created_at,
                $book->authors,
                $book->genres,
            ]);
        }

        fclose($handle);

        return response()->download($filePath, $csvFileName, $headers)->deleteFileAfterSend(true);
    }

    private function getGenresAuthors() {
        return DB::table('books')
            ->join('book_authors', 'books.id', '=', 'book_authors.book_id')
            ->join('authors', 'book_authors.author_id', '=', 'authors.id')
            ->join('book_genres', 'books.id', '=', 'book_genres.book_id')
            ->join('genres', 'book_genres.genre_id', '=', 'genres.id')
            ->select('books.id', 'books.name', 'books.created_at')
            ->selectRaw("string_agg(DISTINCT authors.name, ', ') as authors")
            ->selectRaw("string_agg(DISTINCT genres.genre, ', ') as genres")
            ->groupBy('books.id', 'books.name', 'books.created_at')
            ->get();
    }
}
