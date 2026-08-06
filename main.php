<?php

Document([
  Doctype(),
  Html()->add([
    Head(),
    Body()->add(
      Div()->add(
        "Hello World!!!"
      )
    )
  ])
]);