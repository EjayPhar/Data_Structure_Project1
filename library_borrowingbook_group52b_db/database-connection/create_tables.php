<?php
include 'login/db_connect.php';

// Create books table
$books_sql = "CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20),
    category VARCHAR(100),
    description TEXT,
    image_path VARCHAR(255),
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($books_sql) === TRUE) {
    echo "Books table created successfully.<br>";
} else {
    echo "Error creating books table: " . $conn->error . "<br>";
}

// Create carts table
$carts_sql = "CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, book_id)
)";

if ($conn->query($carts_sql) === TRUE) {
    echo "Carts table created successfully.<br>";
} else {
    echo "Error creating carts table: " . $conn->error . "<br>";
}

// Create borrowings table
$borrowings_sql = "CREATE TABLE IF NOT EXISTS borrowings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    staff_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
)";

if ($conn->query($borrowings_sql) === TRUE) {
    echo "Borrowings table created successfully.<br>";
} else {
    echo "Error creating borrowings table: " . $conn->error . "<br>";
}

// Insert sample books data
$sample_books = [
    [
        'title' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
        'author' => 'Robert C. Martin',
        'isbn' => '978-0-13-235088-4',
        'category' => 'Programming',
        'description' => 'A comprehensive guide to writing clean, maintainable code.',
        'image_path' => 'picture image/Clean Code_ A Handbook of Agile Software Craftsmanship.jpg',
        'total_copies' => 3,
        'available_copies' => 3
    ],
    [
        'title' => 'Introduction to Algorithms',
        'author' => 'Thomas H. Cormen',
        'isbn' => '978-0-262-03384-8',
        'category' => 'Computer Science',
        'description' => 'A comprehensive introduction to computer algorithms.',
        'image_path' => 'picture image/Introduction_to_Algorithms.jpg',
        'total_copies' => 3,
        'available_copies' => 0
    ],
    [
        'title' => 'Modern Physics',
        'author' => 'Stephen Hawking',
        'isbn' => '978-0-553-38016-9',
        'category' => 'Physics',
        'description' => 'A comprehensive introduction to modern physics concepts.',
        'image_path' => 'picture image/modern.jpg',
        'total_copies' => 5,
        'available_copies' => 1
    ],
    [
        'title' => 'The Feynman Lectures on Physics',
        'author' => 'Richard P. Feynman',
        'isbn' => '978-0-465-02493-0',
        'category' => 'Physics',
        'description' => 'A comprehensive set of lectures on physics fundamentals.',
        'image_path' => 'picture image/feyman.jpg',
        'total_copies' => 4,
        'available_copies' => 1
    ],
    [
        'title' => 'The Structure of Scientific Revolutions',
        'author' => 'Thomas S. Kuhn and Ian Hacking',
        'isbn' => '978-0-226-45812-0',
        'category' => 'Philosophy of Science',
        'description' => 'A seminal work on the history and philosophy of science.',
        'image_path' => 'picture image/OIP.jpg',
        'total_copies' => 6,
        'available_copies' => 2
    ],
    [
        'title' => 'The Story of Thomas A. Edison',
        'author' => 'Frances M. Perry',
        'isbn' => '978-0-486-28246-0',
        'category' => 'Biography',
        'description' => 'A biography of the famous inventor Thomas A. Edison.',
        'image_path' => 'picture image/Story.jpg',
        'total_copies' => 8,
        'available_copies' => 4
    ]
];

foreach ($sample_books as $book) {
    $check_sql = "SELECT id FROM books WHERE title = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $book['title']);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows == 0) {
        $insert_sql = "INSERT INTO books (title, author, isbn, category, description, image_path, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssssii",
            $book['title'], $book['author'], $book['isbn'], $book['category'],
            $book['description'], $book['image_path'], $book['total_copies'], $book['available_copies']
        );

        if ($insert_stmt->execute()) {
            echo "Book '{$book['title']}' inserted successfully.<br>";
        } else {
            echo "Error inserting book '{$book['title']}': " . $insert_stmt->error . "<br>";
        }
        $insert_stmt->close();
    } else {
        echo "Book '{$book['title']}' already exists.<br>";
    }
    $check_stmt->close();
}

$conn->close();
echo "Database setup completed.";
?>
