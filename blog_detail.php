<?php 
session_start();
include_once './database/connection.php';
include_once './middleware/middleware.php';
include_once './libraries/jdf.php';

$blog_id = $_POST['blog_id'] ?? $_GET['blog'];

$query = "SELECT * FROM blogs WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $blog_id]);
$blog_detail = $stmt->fetch(PDO::FETCH_ASSOC);

middleware(empty($blog_detail), page: 'index.php');

$query = "SELECT * FROM users WHERE id = :id"; 
$stmt = $db->prepare($query);
$stmt->execute(['id' => $blog_detail['user_id']]);
$writer = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT * FROM categories WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $blog_detail['category']]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT id, title, image, created_at 
          FROM blogs 
          WHERE category = :category AND id != :id
          ORDER BY created_at DESC LIMIT 4";

$stmt_related = $db->prepare($query);
$stmt_related->execute([
    'category' => $blog_detail['category'],
    'id' => $blog_id
]);
$related_result = $stmt_related->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT blog_comments.*, users.username 
          FROM blog_comments
          JOIN users ON users.id = blog_comments.user
          WHERE blog_comments.blog_id = :blog_id AND blog_comments.status = 1
          ORDER BY blog_comments.created_at DESC";

$stmt_comment = $db->prepare($query);
$stmt_comment->execute(['blog_id' => $blog_id]);
$comments = $stmt_comment->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $comment_text = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if ($comment_text !== "") {

        $query = "INSERT INTO blog_comments (blog_id, user, comment, status, created_at)
                  VALUES (:blog_id, :user, :comment, 0, NOW())";

        $stmt_insert = $db->prepare($query);
        $stmt_insert->execute([
            'blog_id' => $blog_id,
            'user' => $user_id,
            'comment' => $comment_text
        ]);

        header("Location: blog_detail.php?blog=$blog_id&pending=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= $blog_detail['title'] ?></title>
  <link rel="stylesheet" href="./assets/css/output.css" />
  <link rel="stylesheet" href="./assets/css/font.css">
</head>

<body class="bg-gray-50">

<?php include_once './components/nav.php' ?>

<?php if (isset($_GET['pending'])): ?>
<script>
Swal.fire({
  icon: 'info',
  title: 'کامنت شما ارسال شد',
  text: 'پس از تأیید ادمین نمایش داده می‌شود'
});
</script>
<?php endif; ?>

<div class="max-w-[1440px] mx-auto px-3">

  <div class="mt-6 p-1 md:p-3">
    <div class="md:flex w-full gap-x-7">

      <div class="w-full md:w-8/12 lg:w-9/12">

        <span class="flex flex-col py-2 px-3 mt-0 max-w-5xl rounded-2xl bg-white">

          <div class="flex flex-wrap gap-x-3 text-xs opacity-75 py-1">

            <div class="flex">
              <div>تاریخ:</div>
              <div class="mr-1"><?= jdate('y/m/d', strtotime($blog_detail['created_at'])) ?></div>
            </div>

            <div class="flex">
              <div>نویسنده:</div>
              <div class="mr-1"><?= $writer['username'] ?></div>
            </div>

            <div class="flex">
              <div>دسته بندی:</div>
              <div class="mr-1"><?= $category['title'] ?></div>
            </div>

          </div>

          <img class="rounded-2xl my-3" 
               src="./assets/image/blog/<?= $blog_detail['image'] ?>" 
               alt="<?= $blog_detail['title'] ?>">

          <div>
            <div class="text-2xl opacity-95 py-3"><?= $blog_detail['title'] ?></div>

            <div class="opacity-70 pb-3 leading-6 text-sm">
              <?= $blog_detail['text'] ?>
            </div>
          </div>

        </span>


        <div class="flex flex-col py-4 px-4 my-6 max-w-5xl rounded-2xl bg-white">

          <div>
            <div>نظرات</div>
            <div class="pr-5 opacity-70 text-xs"><?= count($comments) ?> نظر</div>
          </div>

          <?php if (isset($_SESSION['user_id'])): ?>
          
          <form method="POST" class="bg-gray-50 rounded-xl px-5 py-3 my-3">
            <input type="hidden" name="blog_id" value="<?= $blog_id ?>">

            <textarea name="comment" class="w-full p-3 rounded-xl text-sm" placeholder="نظر خود را بنویسید..."></textarea>

            <button type="submit" class="bg-blue-600 text-white mt-4 px-4 py-2 rounded-xl">
              ثبت نظر
            </button>

          </form>

          <?php else: ?>
          <div class="bg-red-100 text-red-600 p-3 rounded-xl text-sm my-3">
            برای ارسال نظر باید وارد شوید.
            <a href="login.php" class="text-blue-600 underline" style="color: blue;">ورود</a>
          </div>
          <?php endif; ?>

          <?php foreach ($comments as $comment): ?>
          <div class="bg-gray-50 rounded-xl px-5 py-3 my-2">

            <div class="flex items-center">
              <img class="w-10" src="./assets/image/userNotImage.png" alt="">
              <div class="text-sm opacity-60 pr-2">نوشته شده توسط <?= $comment['username'] ?></div>
            </div>

            <div class="opacity-70 text-sm py-3">
              <?= $comment['comment'] ?>
            </div>

          </div>
          <?php endforeach; ?>

        </div>

      </div>


      <div class="w-full md:w-4/12 lg:w-3/12 max-w-xl mx-auto">
        <div class="p-3 space-y-4 bg-white rounded-2xl">

          <div class="opacity-90 border-b pb-3">مرتبط ترین مقاله‌ها:</div>

          <?php foreach($related_result as $related): ?>
          <a href="./blog_detail.php?blog=<?= $related['id'] ?>" class="flex p-1">

            <img class="hover:scale-105 transition rounded-lg w-32"
                 src="./assets/image/blog/<?= $related['image'] ?>" alt="">

            <div class="w-full px-3">
              <div class="text-sm opacity-90"><?= $related['title'] ?></div>
              <div class="text-xs opacity-75"><?= jdate('y/m/d', strtotime($related['created_at'])) ?></div>
            </div>

          </a>
          <?php endforeach; ?>

        </div>
      </div>

    </div>
  </div>

</div>

<?php include_once './components/footer.php' ?>

</body>
</html>