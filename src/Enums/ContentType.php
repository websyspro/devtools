<?php

namespace Websyspro\DevTools\Enums;

enum ContentType: string
{
  case HTML = "html";
  case HTM = "htm";
  case CSS = "css";
  case JS = "js";
  case MJS = "mjs";
  case JSON = "json";
  case XML = "xml";
  case MAP = "map";

  case PNG = "png";
  case JPG = "jpg";
  case JPEG = "jpeg";
  case GIF = "gif";
  case SVG = "svg";
  case WEBP = "webp";
  case AVIF = "avif";
  case BMP = "bmp";
  case ICO  = "ico";
  case TIF = "tif";
  case TIFF = "tiff";

  case MP3 = "mp3";
  case WAV = "wav";
  case OGG = "ogg";
  case OGA = "oga";
  case M4A = "m4a";
  case AAC = "aac";
  case FLAC = "flac";

  case MP4 = "mp4";
  case WEBM = "webm";
  case OGV = "ogv";
  case MOV = "mov";
  case AVI = "avi";
  case M4V = "m4v";

  case WOFF = "woff";
  case WOFF2 = "woff2";
  case TTF = "ttf";
  case OTF = "otf";
  case EOT = "eot";

  case PDF = "pdf";
  case TXT = "txt";
  case CSV = "csv";
  case RTF = "rtf";

  case ZIP = "zip";
  case GZ = "gz";
  case TAR = "tar";
  case SEVEN_Z = "7z";

  case DOC = "doc";
  case DOCX = "docx";
  case XLS  = "xls";
  case XLSX = "xlsx";
  case PPT = "ppt";
  case PPTX = "pptx";

  public function mime(
  ): string {
    return match( $this ){
      ContentType::HTML, ContentType::HTM => "text/html",
      ContentType::CSS => "text/css",
      ContentType::JS, ContentType::MJS => "application/javascript",
      ContentType::JSON, ContentType::MAP => "application/json",
      ContentType::XML => "application/xml",

      ContentType::PNG => "image/png",
      ContentType::JPG, ContentType::JPEG => "image/jpeg",
      ContentType::GIF => "image/gif",
      ContentType::SVG => "image/svg+xml",
      ContentType::WEBP => "image/webp",
      ContentType::AVIF => "image/avif",
      ContentType::BMP => "image/bmp",
      ContentType::ICO => "image/x-icon",
      ContentType::TIF, ContentType::TIFF => "image/tiff",

      ContentType::MP3 => "audio/mpeg",
      ContentType::WAV => "audio/wav",
      ContentType::OGG, ContentType::OGA => "audio/ogg",
      ContentType::M4A => "audio/mp4",
      ContentType::AAC => "audio/aac",
      ContentType::FLAC => "audio/flac",

      ContentType::MP4, ContentType::M4V => "video/mp4",
      ContentType::WEBM => "video/webm",
      ContentType::OGV => "video/ogg",
      ContentType::MOV => "video/quicktime",
      ContentType::AVI => "video/x-msvideo",

      ContentType::WOFF => "font/woff",
      ContentType::WOFF2 => "font/woff2",
      ContentType::TTF => "font/ttf",
      ContentType::OTF => "font/otf",
      ContentType::EOT => "application/vnd.ms-fontobject",

      ContentType::PDF => "application/pdf",
      ContentType::TXT => "text/plain",
      ContentType::CSV => "text/csv",
      ContentType::RTF => "application/rtf",

      ContentType::ZIP => "application/zip",
      ContentType::GZ => "application/gzip",
      ContentType::TAR => "application/x-tar",
      ContentType::SEVEN_Z => "application/x-7z-compressed",

      ContentType::DOC => "application/msword",
      ContentType::DOCX => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      ContentType::XLS => "application/vnd.ms-excel",
      ContentType::XLSX => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      ContentType::PPT => "application/vnd.ms-powerpoint",
      ContentType::PPTX => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
    };
  }
}
