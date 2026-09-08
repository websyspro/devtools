<?php

namespace Websyspro\DevTools\Objects;

use Websyspro\DevTools\Enums\FileExist;
use Websyspro\DevTools\Enums\RequestType;
use Websyspro\DevTools\Enums\ContentType;
use Websyspro\DevTools\Enums\FileType;
use function sprintf;

class PathInfos
{
  public File $file;
  public Directory $directory;
  public FileExist $fileExist;
  public ContentType $contentType;
  public FileType $fileType;

  public function __construct(
    private RequestHandler $requestHandler
  ){
    $this->handler();
    $this->handlerClear();
  }

  public function isStatic(
  ): bool {
    return $this->fileType === FileType::Static;
  }  

  public function handlerStaticResponse(
  ): void {
    header( "Content-Type: {$this->contentType->mime()};charset=UTF-8" );
    readfile( $this->file->name );
  }

  private function handlerExtension(
  ): string {
    return pathinfo( $this->file->name, PATHINFO_EXTENSION );
  }

  private function handlerContentType(
  ): ContentType {
    return ContentType::tryFrom( $this->handlerExtension() ) ?? ContentType::HTML;
  }  

  private function handlerFileType(
  ): FileType {
    return ContentType::tryFrom( $this->handlerExtension() ) !== null
      ? FileType::Static : FileType::Script;
  }

  private function handlerFileExist(
  ): FileExist {
    return file_exists( $this->file->name ) 
      ? FileExist::Yes : FileExist::No;
  }

  private function convertBar(
    string $dir,
    bool $defaultEndBar = false
  ): string {
    if( $defaultEndBar ){
      $directory = str_replace(
        [ "/" ], DIRECTORY_SEPARATOR, rtrim( $dir, "/" )
      );

      return $this->requestHandler->requestType === RequestType::Directory 
        ? $this->convertBar( "{$directory}/index.php" ) 
        : $this->convertBar( "{$directory}" );
    }

    return str_replace(
      [ "/" ], DIRECTORY_SEPARATOR, $dir
    );
  }

  private function isDirectory(
  ): bool {
    return $this->requestHandler->requestType 
       === RequestType::Directory;
  }

  private function isFileExists(
  ): bool {
    return file_exists( $this->file->name );
  }

  private function friendlyUrl(
  ): bool {
    return $this->requestHandler->friendlyUrl;
  }

  private function handlerFile(
  ): void {
    $this->file = new File(
      sprintf( "%s%s",
        $this->convertBar( $this->requestHandler->directory ),
        $this->convertBar( $this->requestHandler->uri, true )
      )
    );

    if( $this->isDirectory() === true ){
      if( $this->isFileExists() === false ){
        if( $this->friendlyUrl() === true ){
          $this->file = new File(
            $this->convertBar( 
              $this->requestHandler->directory, true
            )
          );
        }
      }
    }

    $this->contentType = $this->handlerContentType();
    $this->fileExist = $this->handlerFileExist();
    $this->fileType = $this->handlerFileType();
  }

  private function handlerDir(
  ): void {
    $this->directory = new Directory(
      sprintf( "%s%s", dirname( $this->file->name ), DIRECTORY_SEPARATOR )
    );
  }

  private function handlerClear(
  ): void {
    unset( $this->requestHandler ); 
  }



  private function handler(
  ): void {
    $this->handlerFile();
    $this->handlerDir();
  }
}