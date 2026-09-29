<?php
require_once 'connection.php';
$cat = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
?>
<nav>
    <a href="/lustro_books/" class="<?php if ($cat == 0) echo 'active'; ?>">Home</a>
    <?php
    $categoryLinks = [1 => 'Novels', 2 => 'Childrens-Books', 3 => 'Student-Books'];
    $cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY id");
    while ($row = mysqli_fetch_assoc($cats)) {
        $class = ($cat == $row['id']) ? 'active' : '';
        $link = '/lustro_books/' . $categoryLinks[$row['id']];
        echo '<a class="' . $class . '" href="' . htmlspecialchars($link) . '">' . htmlspecialchars($row['title']) . '</a>';
    }
    ?>
</nav>
