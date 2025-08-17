<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookController extends Controller
{
    protected $metaPath;
    protected $pdfDir;

    public function __construct()
    {
        $this->metaPath = storage_path('app/books/metadata.json');
        $this->pdfDir = storage_path('app/books/pdfs');

        if (!is_dir(dirname($this->metaPath))) mkdir(dirname($this->metaPath), 0777, true);
        if (!is_dir($this->pdfDir)) mkdir($this->pdfDir, 0777, true);
        if (!file_exists($this->metaPath)) file_put_contents($this->metaPath, json_encode([]));
    }

    protected function readMeta(): array
    {
        $json = file_get_contents($this->metaPath);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    protected function writeMeta(array $data): void
    {
        file_put_contents($this->metaPath, json_encode(array_values($data), JSON_PRETTY_PRINT));
    }

    // 📚 GET /books
    public function index(Request $req)
    {
        $data = $this->readMeta();

        if ($req->has('class')) {
            $data = array_filter($data, fn($b) => ($b['class'] ?? '') == $req->query('class'));
        }
        if ($req->has('category')) {
            $data = array_filter($data, fn($b) => ($b['category'] ?? '') == $req->query('category'));
        }
        if ($req->has('q')) {
            $q = strtolower($req->query('q'));
            $data = array_filter($data, fn($b) =>
                strpos(strtolower($b['title'] ?? ''), $q) !== false ||
                strpos(strtolower($b['author'] ?? ''), $q) !== false
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Books fetched successfully',
            'count' => count($data),
            'data' => array_values($data)
        ]);
    }

    // 📖 GET /books/{id}
    public function show($id)
    {
        $data = $this->readMeta();
        $book = collect($data)->firstWhere('id', $id);
        if (!$book) {
            return response()->json([
                'status' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Book details retrieved',
            'data' => $book
        ]);
    }

    // 📥 GET /books/{id}/download
    public function download($id)
    {
        $data = $this->readMeta();
        $book = collect($data)->firstWhere('id', $id);
        if (!$book) {
            return response()->json([
                'status' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        $filePath = $this->pdfDir . '/' . $book['filename'];
        if (!file_exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'File missing',
                'data' => null
            ], 410);
        }

        return response()->download($filePath, $book['filename']);
    }

    // ➕ POST /books
    public function store(Request $req)
    {
        $req->validate([
            'title' => 'required|string|max:255',
            'class' => 'required|string',
            'category' => 'required|string',
            'author' => 'nullable|string|max:255',
            'pdf' => 'required|file|mimes:pdf|max:20480'
        ]);

        $id = 'b' . Str::random(8);
        $filename = $id . '.pdf';
        $req->file('pdf')->move($this->pdfDir, $filename);

        $meta = $this->readMeta();
        $entry = [
            'id' => $id,
            'title' => $req->input('title'),
            'class' => $req->input('class'),
            'category' => $req->input('category'),
            'author' => $req->input('author', null),
            'filename' => $filename,
            'uploaded_at' => now()->toIso8601String(),
        ];
        $meta[] = $entry;
        $this->writeMeta($meta);

        return response()->json([
            'status' => true,
            'message' => 'Book uploaded successfully',
            'data' => $entry
        ], 201);
    }

    // ✏️ PUT/PATCH /books/{id}
    public function update(Request $req, $id)
    {
        $req->validate([
            'title' => 'sometimes|string|max:255',
            'class' => 'sometimes|string',
            'category' => 'sometimes|string',
            'author' => 'sometimes|nullable|string|max:255',
            'pdf' => 'sometimes|file|mimes:pdf|max:20480'
        ]);

        $meta = $this->readMeta();
        $foundIndex = null;
        foreach ($meta as $i => $m) {
            if ($m['id'] === $id) { $foundIndex = $i; break; }
        }
        if ($foundIndex === null) {
            return response()->json([
                'status' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        if ($req->hasFile('pdf')) {
            $old = $meta[$foundIndex]['filename'] ?? null;
            if ($old && file_exists($this->pdfDir.'/'.$old)) unlink($this->pdfDir.'/'.$old);

            $filename = $id . '.pdf';
            $req->file('pdf')->move($this->pdfDir, $filename);
            $meta[$foundIndex]['filename'] = $filename;
        }

        foreach (['title', 'class', 'category', 'author'] as $f) {
            if ($req->filled($f) || $req->has($f)) {
                $meta[$foundIndex][$f] = $req->input($f);
            }
        }
        $meta[$foundIndex]['uploaded_at'] = now()->toIso8601String();
        $this->writeMeta($meta);

        return response()->json([
            'status' => true,
            'message' => 'Book updated successfully',
            'data' => $meta[$foundIndex]
        ]);
    }

    // ❌ DELETE /books/{id}
    public function destroy($id)
    {
        $meta = $this->readMeta();
        $foundIndex = null;
        foreach ($meta as $i => $m) {
            if ($m['id'] === $id) { $foundIndex = $i; break; }
        }
        if ($foundIndex === null) {
            return response()->json([
                'status' => false,
                'message' => 'Book not found',
                'data' => null
            ], 404);
        }

        $filename = $meta[$foundIndex]['filename'] ?? null;
        if ($filename && file_exists($this->pdfDir.'/'.$filename)) {
            unlink($this->pdfDir.'/'.$filename);
        }

        array_splice($meta, $foundIndex, 1);
        $this->writeMeta($meta);

        return response()->json([
            'status' => true,
            'message' => 'Book deleted successfully',
            'data' => null
        ]);
    }
}
