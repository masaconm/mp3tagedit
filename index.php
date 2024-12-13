<?php
header( 'Content-Type: text/html; charset=UTF-8' );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/james-heinrich/getid3/getid3/getid3.php';
require_once __DIR__ . '/vendor/james-heinrich/getid3/getid3/write.php';

$getID3 = new getID3();

use getID3;
use getid3_writetags;

$uploadDir = __DIR__ . '/uploads/';
if ( !is_dir( $uploadDir ) ) {
  mkdir( $uploadDir, 0777, true );
}

// タグ保存処理
if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] === 'save' ) {
  $files = $_POST[ 'files' ] ?? [];
  $titles = $_POST[ 'title' ] ?? [];
  $artists = $_POST[ 'artist' ] ?? [];
  $albums = $_POST[ 'album' ] ?? [];
  $years = $_POST[ 'year' ] ?? [];
  $genres = $_POST[ 'genre' ] ?? [];
  $tracks = $_POST[ 'track_number' ] ?? [];
  $newFilenames = $_POST[ 'newfilename' ] ?? [];
  $artworks = $_FILES[ 'artwork' ] ?? null;

  echo "<!DOCTYPE html>";
  echo "<html lang='ja'>";
  echo "<head>
            <meta charset='UTF-8'>
            <title>Save Results</title>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>";
  echo "<body class='bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 min-h-screen p-6'>";

  // ヘッダーを追加
  echo "<div class='container mx-auto flex flex-col lg:flex-row justify-between items-center mb-6'>
            <div class='text-white font-bold text-3xl hover:text-orange-600'>AudioTag Editor Beta</div>
        </div>";

  echo "<div class='max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-lg overflow-x-auto'>";
  echo "<h1 class='text-2xl font-bold text-pink-700 mb-4'>Tag Update Results</h1>";


  foreach ( $files as $index => $filePath ) {
    if ( !file_exists( $filePath ) ) continue;

    // ファイル情報を取得して既存のアートワークを確認
    $fileInfo = $getID3->analyze( $filePath );
    $existingArtwork = $fileInfo[ 'id3v2' ][ 'APIC' ][ 0 ] ?? null;

    // タグデータの準備
    $TagData = [
      'title' => [ mb_convert_encoding( $titles[ $index ], 'UTF-8' ) ],
      'artist' => [ mb_convert_encoding( $artists[ $index ], 'UTF-8' ) ],
      'album' => [ mb_convert_encoding( $albums[ $index ], 'UTF-8' ) ],
      'year' => [ $years[ $index ] ],
      'genre' => [ mb_convert_encoding( $genres[ $index ], 'UTF-8' ) ],
      'track_number' => [ $tracks[ $index ] ]
    ];

    // アートワークの設定
    if ( $artworks && isset( $artworks[ 'tmp_name' ][ $index ] ) && is_uploaded_file( $artworks[ 'tmp_name' ][ $index ] ) ) {
      // 新しいアートワークがアップロードされた場合
      $artworkData = file_get_contents( $artworks[ 'tmp_name' ][ $index ] );
      $finfo = new finfo( FILEINFO_MIME_TYPE );
      $mimeType = $finfo->file( $artworks[ 'tmp_name' ][ $index ] );

      $TagData[ 'attached_picture' ][ 0 ] = [
        'data' => $artworkData,
        'picturetypeid' => 0x03,
        'description' => 'cover',
        'mime' => $mimeType
      ];
    } elseif ( $existingArtwork ) {
      // 既存のアートワークを保持
      $TagData[ 'attached_picture' ][ 0 ] = [
        'data' => $existingArtwork[ 'data' ],
        'picturetypeid' => 0x03,
        'description' => 'cover',
        'mime' => $existingArtwork[ 'image_mime' ]
      ];
    }

    $tagwriter = new getid3_writetags();
    $tagwriter->filename = $filePath;
    $tagwriter->tagformats = [ 'id3v2.4', 'id3v1' ];
    $tagwriter->overwrite_tags = true;
    $tagwriter->remove_other_tags = false;
    $tagwriter->tag_data = $TagData;

    if ( $tagwriter->WriteTags() ) {
      echo "<p class='text-green-600 font-semibold'>✔ Successfully updated tags for: "
        . htmlspecialchars( basename( $filePath ), ENT_QUOTES, 'UTF-8' ) . "</p>";
    } else {
      echo "<p class='text-red-600 font-semibold'>✘ Error updating tags for: "
        . htmlspecialchars( basename( $filePath ), ENT_QUOTES, 'UTF-8' ) . "<br>";
      echo "Errors: " . implode( '<br>', $tagwriter->errors ) . "</p>";
    }

    // ファイル名変更処理
    $currentFileName = basename( $filePath );
    $newName = trim( $newFilenames[ $index ] );
    if ( !empty( $newName ) && $newName !== $currentFileName ) {
      if ( strtolower( pathinfo( $newName, PATHINFO_EXTENSION ) ) !== 'mp3' ) {
        $newName .= '.mp3';
      }
      $newPath = $uploadDir . $newName;
      if ( rename( $filePath, $newPath ) ) {
        $filePath = $newPath; // ファイルパスを更新
        echo "<p class='text-blue-600'>↪ File renamed to: "
          . htmlspecialchars( $newName, ENT_QUOTES, 'UTF-8' ) . "</p>";
      } else {
        echo "<p class='text-red-600'>⚠ Error renaming file: "
          . htmlspecialchars( $newName, ENT_QUOTES, 'UTF-8' ) . "</p>";
      }
    }

    // ZIPファイル作成用リストに追加
    $editedFiles[] = $filePath;
  }

  // ZIPファイルの作成
  $zip = new ZipArchive(); // ZipArchive のインスタンスを作成
  $zipFilename = $uploadDir . 'edited_files.zip'; // ZIPファイルの保存先パス

  if ( $zip->open( $zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
    foreach ( $editedFiles as $editedFile ) {
      if ( file_exists( $editedFile ) ) { // ファイルが存在するか確認
        $zip->addFile( $editedFile, basename( $editedFile ) ); // ファイルをZIPに追加
      }
    }
    $zip->close(); // ZIPを閉じる
    echo "<p class='text-green-600'>✔ ZIPファイルを作成しました。</p>";

    // ダウンロードリンクの作成
    $zipDownloadLink = '/mp3tagedit/uploads/edited_files.zip'; // 公開URLに合わせる

    echo "<div class='w-full md:w-1/2 p-2 mt-4'>
                <a href='$zipDownloadLink' class='flex flex-wrap justify-center w-full px-4 py-2.5 bg-pink-500 hover:bg-pink-600 font-medium text-base text-white rounded-md shadow-button'>
                    Download ZIP
                </a>
            </div>";

    // 初期画面に戻るボタンをDownload ZIPボタンの下に配置
    echo "<div class='w-full md:w-1/2 p-2 mt-2'> <!-- mt-4でボタン間に余白を追加 -->
                <a href='index.php' class='flex justify-center items-center px-6 py-2 bg-pink-100 text-pink-700 font-medium text-base hover:bg-pink-200 border border-coolGray-200 hover:border-coolGray-300 rounded-md shadow-button'>
                    Go back to upload form
                </a>
            </div>";
  } else {
    // ZIP作成エラー時の処理
    echo "<p class='text-red-600'>✘ ZIPファイルの作成に失敗しました。</p>";
    echo "<pre>Path: " . htmlspecialchars( $zipFilename, ENT_QUOTES, 'UTF-8' ) . "</pre>";
  }


} // ファイルアップロード処理
if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' && isset( $_FILES[ 'mp3files' ] ) ) {
  $uploadedFiles = [];
  foreach ( $_FILES[ 'mp3files' ][ 'tmp_name' ] as $i => $tmpName ) {
    if ( is_uploaded_file( $tmpName ) ) {
      $fileName = basename( $_FILES[ 'mp3files' ][ 'name' ][ $i ] );
      $filePath = $uploadDir . $fileName;
      if ( move_uploaded_file( $tmpName, $filePath ) ) {
        $uploadedFiles[] = $filePath;
      }
    }
  }

  if ( !empty( $uploadedFiles ) ) {
    $getID3 = new getID3();
    echo "<!DOCTYPE html>";
    echo "<html lang='ja'>";
    echo "<head>
                <meta charset='UTF-8'>
                <title>Edit Multiple MP3 ID3 Tags</title>
                <script src='https://cdn.tailwindcss.com'></script>
                <script>
                  document.addEventListener('DOMContentLoaded', () => {
                    // 画像クリックでhiddenなfile inputをclick
                    document.querySelectorAll('.artwork-img').forEach((img, idx) => {
                      img.addEventListener('click', () => {
                        const fileInput = document.querySelector('input[name=\"artwork[]\"][data-idx=\"' + idx + '\"]');
                        if (fileInput) {
                          fileInput.click();
                        }
                      });
                    });

                    // 選択した画像を即時プレビュー
                    document.querySelectorAll('input[name=\"artwork[]\"]').forEach(fileInput => {
                      fileInput.addEventListener('change', () => {
                        if (fileInput.files && fileInput.files[0]) {
                          const reader = new FileReader();
                          reader.onload = (e) => {
                            const idx = fileInput.getAttribute('data-idx');
                            const img = document.querySelector('.artwork-img[data-idx=\"' + idx + '\"]');
                            if (img) {
                              img.src = e.target.result;
                            }
                          };
                          reader.readAsDataURL(fileInput.files[0]);
                        }
                      });
                    });

                    // File Nameをクリックで編集可能にする
                    document.querySelectorAll('.filename-cell').forEach((cell, idx) => {
                      const span = cell.querySelector('.filename-text');
                      const input = cell.querySelector('input[name=\"newfilename[]\"]');

                      span.addEventListener('click', () => {
                        // spanを非表示、inputを表示してフォーカス
                        span.classList.add('hidden');
                        input.classList.remove('hidden');
                        input.focus();
                      });
                    });
                  });
                </script>
              </head>";
    echo "<body class='bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 min-h-screen p-6'>";
    // ヘッダーを追加
    echo "<div class='container mx-auto flex flex-col lg:flex-row justify-between items-center mb-6'>
                <div class='text-white font-bold text-3xl hover:text-orange-600'>AudioTag Editor Beta</div>
            </div>";
    echo "<div class='max-w-8xl mx-auto bg-white p-8 rounded-lg shadow-lg'>";
    echo "<h2 class='text-2xl font-bold text-pink-700 mb-4'>Uploaded MP3 Files - Edit ID3 Tags</h2>";
    echo "<form method='POST' action='' enctype='multipart/form-data' class='space-y-4'>";
    echo "<input type='hidden' name='action' value='save'>";

    // 横スクロールを可能にするコンテナ
    echo "<div class='overflow-x-auto'>";
    echo "<table class='table-auto min-w-max border-collapse text-sm border border-pink-200'>";
    echo "<thead>
                      <tr class='bg-pink-100 text-pink-700'>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[170px]'>File Name</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[250px]'>Title</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[180px]'>Artist</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[300px]'>Album</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[80px]'>Year</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[80px]'>Genre</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[80px]'>Track #</th>
                        <th class='border border-pink-200 px-3 py-2 text-left w-[80px]'>Artwork</th>
                      </tr>
                    </thead>";
    echo "<tbody class='bg-white'>";

    foreach ( $uploadedFiles as $idx => $filePath ) {
      $fileInfo = $getID3->analyze( $filePath );
      getid3_lib::CopyTagsToComments( $fileInfo );

      $currentTitle = $fileInfo[ 'comments' ][ 'title' ][ 0 ] ?? '';
      $currentArtist = $fileInfo[ 'comments' ][ 'artist' ][ 0 ] ?? '';
      $currentAlbum = $fileInfo[ 'comments' ][ 'album' ][ 0 ] ?? '';
      $currentYear = $fileInfo[ 'comments' ][ 'year' ][ 0 ] ?? '';
      $currentGenre = $fileInfo[ 'comments' ][ 'genre' ][ 0 ] ?? '';
      $currentTrack = $fileInfo[ 'comments' ][ 'track_number' ][ 0 ] ?? '';

      $artworkHtml = '';
      if ( !empty( $fileInfo[ 'comments' ][ 'picture' ][ 0 ] ) ) {
        $picture = $fileInfo[ 'comments' ][ 'picture' ][ 0 ];
        $mime = $picture[ 'image_mime' ];
        $data = base64_encode( $picture[ 'data' ] );
        $artworkHtml = "<img src='data:$mime;base64,$data' class='w-12 h-12 object-cover rounded artwork-img cursor-pointer' data-idx='$idx'>";
      } else {
        $artworkHtml = "<div class='w-12 h-12 flex items-center justify-center bg-gray-200 text-gray-600 rounded artwork-img cursor-pointer' data-idx='$idx'>No Img</div>";
      }

      $fileName = basename( $filePath );

      echo "<tr class='hover:bg-pink-50 transition duration-200'>";
      // File Name列: 初期はspan表示、クリックでinputが表示
      echo "<td class='border border-pink-200 px-3 py-2 filename-cell'>
                    <span class='filename-text cursor-pointer'>" . htmlspecialchars( $fileName, ENT_QUOTES, 'UTF-8' ) . "</span>
                    <input type='text' name='newfilename[]' value='" . htmlspecialchars( $fileName, ENT_QUOTES, 'UTF-8' ) . "' class='hidden w-full border-pink-300 rounded px-2 py-1 mt-1'>
                    <input type='hidden' name='files[]' value='" . htmlspecialchars( $filePath, ENT_QUOTES, 'UTF-8' ) . "'>
                  </td>";
      echo "<td class='border border-pink-200 px-3 py-2'><input type='text' name='title[]' value='" . htmlspecialchars( $currentTitle, ENT_QUOTES, 'UTF-8' ) . "' class='w-full border-pink-300 rounded px-2 py-1'></td>";
      echo "<td class='border border-pink-200 px-3 py-2'><input type='text' name='artist[]' value='" . htmlspecialchars( $currentArtist, ENT_QUOTES, 'UTF-8' ) . "' class='w-full border-pink-300 rounded px-2 py-1'></td>";
      echo "<td class='border border-pink-200 px-3 py-2'><input type='text' name='album[]' value='" . htmlspecialchars( $currentAlbum, ENT_QUOTES, 'UTF-8' ) . "' class='w-full border-pink-300 rounded px-2 py-1'></td>";
      echo "<td class='border border-pink-200 px-3 py-2'><input type='text' name='year[]' value='" . htmlspecialchars( $currentYear, ENT_QUOTES, 'UTF-8' ) . "' class='w-full border-pink-300 rounded px-2 py-1'></td>";
      echo "<td class='border border-pink-200 px-3 py-2'><input type='text' name='genre[]' value='" . htmlspecialchars( $currentGenre, ENT_QUOTES, 'UTF-8' ) . "' class='w-full border-pink-300 rounded px-2 py-1'></td>";
      echo "<td class='border border-pink-200 px-3 py-2'><input type='text' name='track_number[]' value='" . htmlspecialchars( $currentTrack, ENT_QUOTES, 'UTF-8' ) . "' class='w-full border-pink-300 rounded px-2 py-1'></td>";
      echo "<td class='border border-pink-200 px-3 py-2 text-center relative'>$artworkHtml<input type='file' name='artwork[]' accept='image/*' class='hidden' data-idx='$idx'></td>";
      echo "</tr>";
    }

    echo "</tbody></table>";
    echo "</div>";
    echo "<br>";
    echo "<button type='submit' class='px-6 py-2 bg-pink-500 text-white font-semibold rounded hover:bg-pink-600'>Save Changes</button>";
    echo "</form>";
    echo "</div></body></html>";
  } else {
    echo "<!DOCTYPE html>";
    echo "<html lang='ja'>";
    echo "<head>
                <meta charset='UTF-8'>
                <title>No Files Uploaded</title>
                <script src='https://cdn.tailwindcss.com'></script>
              </head>";
    echo "<body class='bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 min-h-screen p-6'>";
    echo "<div class='max-w-lg mx-auto bg-white p-8 rounded-lg shadow-lg'>";
    echo "<p class='text-gray-800'>No files were uploaded.</p>";
    echo "<a href='index.php' class='inline-block mt-4 px-4 py-2 bg-pink-500 text-white rounded hover:bg-pink-600'>Go back</a>";
    echo "</div></body></html>";
  }

  exit;
}

// 初期画面（アップロードフォーム表示）
if ( !isset( $_POST[ 'action' ] ) ) { // この条件を追加
  ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>MP3 ID3 Tag Editor</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 min-h-screen p-6">
<div class="container mx-auto flex flex-col lg:flex-row justify-between items-center">
  <div class="text-white font-bold text-3xl mb-4 lg:mb-10 hover:text-orange-600">AudioTag Editor Beta </div>
</div>
<div class="max-w-lg mx-auto bg-white p-8 rounded-lg shadow-lg  mt-10">
  <h1 class="text-2xl font-bold text-pink-700 mb-8">Upload Multiple MP3 Files</h1>
  <form method="post" action="" enctype="multipart/form-data" class="space-y-4">
    <input type="file" name="mp3files[]" multiple accept=".mp3"
                   class="block w-full text-sm text-gray-500
                          file:mr-4 file:py-2 file:px-4 file:rounded file:border-0
                          file:text-sm file:bg-pink-100 file:text-pink-700
                          hover:file:bg-pink-200 focus:outline-none focus:ring-2 focus:ring-pink-500">
    <button type="submit" class="px-6 py-2 bg-pink-500 text-white font-semibold rounded hover:bg-pink-600">Upload</button>
  </form>
</div>
</body>
</html>
<?php
}