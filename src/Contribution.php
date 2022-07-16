<?php

namespace Dosdashboard;

interface Contribution {

  public const CONTRIBUTION_TITLES = [
    'user_email',
    'user_name',
    'user_country',
    'user_url',
    'user_fio',
    'contrib_url',
    'contrib_date',
    'contrib_type',
    'contrib_description',
  ];

  public function getPushes();
}
