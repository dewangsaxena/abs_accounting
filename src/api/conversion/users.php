<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
class Users extends Common {

    private static function format(array $old): array {
        $new = [];
        $new['id'] = $old['id'];
        $new['name'] = $old['_name'];
        $new['username'] = $old['_username'];
        $new['password'] = $old['password_hash'];
        $new['access_level'] = $old['role_id'];
        $new['store_id'] = $old['store_id'];
        $new['has_access'] = $old['has_access'];
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    public static function read(int $from, ?int $till): array {
        $db = get_old_db_instance();

        $query = 'SELECT * FROM users WHERE id >= :_from ';
        $values = [':_from' => $from];
        if(is_numeric($till)) {
            $query .= ' AND id <= :_till ';
            $values[':_till'] = $till;
        }

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $users = [];

        foreach($records as $record) {
            $users []= self::format($record);
        }

        return $users;
    }

    public static function write(array $records): void {
        $db = get_db_instance();
        try {
            $query = <<<'EOS'
            INSERT INTO users
            (
                `id`,
                `name`,
                `username`,
                `password`,
                `access_level`,
                `store_id`,
                `has_access`,
                `created`,
                `modified`
            )
            VALUES
            (
                :id,
                :name,
                :username,
                :password,
                :access_level,
                :store_id,
                :has_access,
                :created,
                :modified
            );
            EOS;
            $db -> beginTransaction();
            $statement = $db -> prepare($query);
            foreach($records as $record) {
                $statement = $db -> prepare($query);
                $values = self::add_colon_before_keyname($record);
                $statement -> execute($values);
                if($db -> lastInsertId() === false) throw new Exception('UNABLE TO INSERT');
            }
            $db -> commit();
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            echo $e -> getMessage();
        }
    }
}
?>